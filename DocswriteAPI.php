<?php

if ( ! class_exists( 'DocswriteAPI' ) ) {
	class DocswriteAPI {
		/**
		 * Map of command names to static methods.
		 *
		 * @var array
		 */
		private static $method_map = [
			'connect'                => 'connect',
			'publish_posts'          => 'publish_posts',
			'disconnect'             => 'disconnect',
			'update_posts'           => 'update_posts',
			'delete_posts'           => 'delete_posts',
			'get_authors'            => 'get_authors',
			'get_taxonomies_terms'   => 'get_taxonomies_terms',
			'get_categories'         => 'get_categories',
			'get_tags'               => 'get_tags',
			'check_connection_status'=> 'check_connection_status',
		];
	
		/**
		 * @return void
		 */
		public static function register_api_endpoints() {
			// Add the endpoint trigger for non-logged in users
			add_action( 'wp_ajax_nopriv_docswrite', array( 'DocswriteAPI', 'execute' ) );
		}

		/**
		 * Execute the request. This structure is very easy to extend - just add new methods in the class, nothing more.
		 *
		 * @return void
		 */
		public static function execute() {
			// Check if the request is POST
			if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
				wp_send_json_error( [
					'message' => 'Invalid request method. Only POST allowed.'
				], 405 );
				wp_die();
			}

			// Decode the input from JSON
			$json    = file_get_contents( 'php://input' );
			$request = json_decode( $json, true );

			// Validate JSON and check required fields
			if (json_last_error() !== JSON_ERROR_NONE) {
				wp_send_json_error([
					'message' => 'Invalid JSON format.'
				], 400);
				wp_die();
			}

			// Ensure the 'action' element is set and 'data' is an array.
			if ( isset( $request['command'] ) && is_array( $request['data'] ) ) {
				// Checking security - for correct UUID
				if ( $request['command'] != 'connect' && ! self::check_uuid( $request['data'] ) ) {
					wp_send_json_error( [
						'message' => 'Wrong UUID or manually disconnected'
					] );
					wp_die();
				}

				$action = $request['command'];
				$data   = $request['data'];

				
				// Check if the action is in the method map
				if (array_key_exists($action, self::$method_map)) {
					$method = self::$method_map[$action];
		
					// Call the static method if it exists
					if (method_exists(__CLASS__, $method)) {
						call_user_func(array(__CLASS__, $method), $data);
					} else {
						wp_send_json_error([
							'message' => 'Method not found.'
						], 500);
					}
				} else {
					wp_send_json_error([
						'message' => 'Invalid command.'
					], 400);
				}
			}
			
			wp_die();

		}

		/**
		 * Check every request for correct UUID
		 *
		 * @param $request_data
		 *
		 * @return bool
		 */
		public static function check_uuid( $request_data ) {
			if ( Docswrite::is_connected() && isset( $request_data['uuid'] ) && $request_data['uuid'] && $request_data['uuid'] === Docswrite::get_website_id() ) {
				return true;
			}

			return false;
		}

		/**
		 * Connecting that ID?
		 * */
		public static function connect( $request_data ) {
			if ( isset( $request_data['uuid'] ) && $request_data['uuid'] && $request_data['uuid'] == Docswrite::get_website_id() ) {
				if ( update_option( Docswrite::DOCSWRITE_CONNECTION_OPTION, $request_data['uuid'] ) ) {
					wp_send_json_success( [
						'message' => 'Connected successfully'
					] );
				} else {
					wp_send_json_error( [
						'message' => 'Already connected'
					] );
				}
				wp_die();
			}
		}

		/**
		 * Status that connection
		 * @return void
		 * */
		public static function check_connection_status() {
			$status = ((bool) get_option( Docswrite::DOCSWRITE_CONNECTION_OPTION )) ? 'Connected' : 'Disconnected';

			wp_send_json_success( [
				'message' => $status
			] );
		
			wp_die();
		}

		/**
		 * Handles disconnection request from the user.
		 *
		 * @return void
		 */
		public static function disconnect() {
			update_option( Docswrite::DOCSWRITE_CONNECTION_OPTION, 0 );
			wp_send_json_success( [
				'message' => 'successfully disconnected'
			] );

			wp_die();
		}

		/**
		 * Search post by Docswrite::DOCSWRITE_POST_ID_META_KEY
		 *
		 * @param $docswrite_id
		 *
		 * @return int[]|WP_Post[]|null
		 */
		public static function get_post_by_docswrite_id( $docswrite_id ) {
			$docswrite_id = intval($docswrite_id);

			if ($docswrite_id <= 0) {
				return [];
			}
		
			// Query for posts with the given meta key and value
			$posts = get_posts(array(
				'meta_key'   => Docswrite::DOCSWRITE_POST_ID_META_KEY,
				'meta_value' => $docswrite_id,
				'post_type'  => 'any',
				'numberposts' => -1,
				'post_status' => 'any',
			));
		
			// If no posts found, return an empty array
			if (empty($posts)) {
				return [];
			}
		
			return $posts;
		}

		/**
		 * Publishes multiple posts.
		 *
		 * @param array $request_data The data containing the posts to be published.
		 *
		 * @return void
		 */
		public static function publish_posts( $request_data ) {
			if ( ! is_array( $request_data['posts'] ) || ! $request_data['posts'] ) {
				wp_send_json_error( [ 'message' => 'No posts in request' ] );
				wp_die();
			}
			$posts_published  = 0;
			$posts_permalinks = [];

			foreach ( $request_data['posts'] as $post_data ) {
				// Prepare post data
				$new_post = array(
					'post_title'    => sanitize_text_field( $post_data['title'] ),
					'post_name'     => sanitize_title_with_dashes( $post_data['slug'] ), // For slugs, this must be activated: https://icecream.me/864fbcf145c46b76999bc36548e29bfa
					'tags_input'    => $post_data['tags'],
					'post_status'   => sanitize_text_field( $post_data['state'] ),
					'post_author'   => (int) $post_data['author'], // it must be an INT
					'post_date'     => date( 'Y-m-d H:i:s', strtotime( $post_data['date'] ) ),
					'post_excerpt'  => sanitize_text_field( $post_data['excerpt'] ),
					'post_type'     => sanitize_text_field( $post_data['post_type'] ),
					'post_category' => $post_data['categories'],
					'post_content'  => wp_kses_post( $post_data['content'] ),
				);

				// Insert post into database
				$post_id = wp_insert_post( $new_post );

				if ( $post_id ) {
					update_post_meta( $post_id, Docswrite::DOCSWRITE_POST_ID_META_KEY, $post_id );
					update_post_meta( $post_id, Docswrite::DOCSWRITE_POST_RAW_META_KEY, $post_data );

					// Download and attach featured image to post
					self::set_image_to_post( $post_data['featured_image_url'], $post_id, $post_data['featured_image_alt_text'], $post_data['featured_image_caption'] );

					// Set post metadata
					if ( $post_data['yoast_settings'] ) {
						$post_data['yoast_settings_clean'] = [];
						foreach ( $post_data['yoast_settings'] as $tmp_key => $tmp_value ) {
							$post_data['yoast_settings_clean'][ str_replace( 'yoast_', '', $tmp_key ) ] = $tmp_value;
						}
						self::setup_metadata( '_yoast_wpseo_', $post_data['yoast_settings_clean'], $post_id );
					}
					if ( $post_data['rankmath_settings'] ) {
						self::setup_metadata( '', $post_data['rankmath_settings'], $post_id );
					}
					if ( $post_data['newspack_settings'] ) {
						self::setup_metadata( '', $post_data['newspack_settings'], $post_id );
					}

					// Add permalink for $post_id to $posts_permalinks[]
					$posts_permalinks[ $post_id ] = get_permalink( $post_id );
					$posts_published ++;
				}
			}

			if ( $posts_published ) {
				wp_send_json_success( [
					'message'          => 'Posts published: ' . $posts_published,
					'posts_permalinks' => $posts_permalinks,
				] );
			} else {
				wp_send_json_error( [
					'message' => 'No posts updated'
				] );
			}

			wp_die();
		}

		/**
		 * Set categories and create if they don't exist
		 *
		 * @param string $categories_string The string containing comma-separated category names.
		 *
		 * @return array The array of category IDs.
		 */
		private static function setup_categories( $categories_string ) {
			$categories_array = explode( ',', $categories_string );
			$categories_ids   = [];

			foreach ( $categories_array as $category_name ) {
				$category_id = get_cat_ID( $category_name );
				if ( $category_id == 0 ) {
					$new_category_id  = wp_create_category( $category_name );
					$categories_ids[] = $new_category_id;
				} else {
					$categories_ids[] = $category_id;
				}
			}

			return $categories_ids;
		}

		/**
		 * Donwload and set image to post
		 *
		 * @param $url
		 * @param $post_id
		 * @param $featured_image_alt_text
		 * @param $featured_image_caption
		 *
		 * @return void
		 */
		public static function set_image_to_post( $url, $post_id, $featured_image_alt_text, $featured_image_caption ) {
			require_once( ABSPATH . 'wp-admin/includes/media.php' );
			require_once( ABSPATH . 'wp-admin/includes/file.php' );
			require_once( ABSPATH . 'wp-admin/includes/image.php' );

			$featured_image_id = media_sideload_image( $url, $post_id, '', 'id' );
			set_post_thumbnail( $post_id, $featured_image_id );

			$featured_image_post = array(
				'ID'           => $featured_image_id,
				'post_title'   => sanitize_text_field( $featured_image_caption ),
				'post_excerpt' => sanitize_text_field( $featured_image_caption ),
			);

			// Update the post into the database
			wp_update_post( $featured_image_post );

			update_post_meta( $post_id, '_wp_attachment_image_alt', sanitize_text_field( $featured_image_alt_text ) );
		}

		/**
		 * Sets up metadata for a given post.
		 *
		 * @param string $metadata_prefix The prefix to prepend to each metadata key.
		 * @param array $metadata_array The array of metadata to set.
		 * @param integer $post_id The ID of the post to set metadata for.
		 *
		 * @return void
		 */
		private static function setup_metadata( $metadata_prefix, $metadata_array, $post_id ) {
			foreach ( $metadata_array as $key => $value ) {
				update_post_meta( $post_id, $metadata_prefix . $key, $value );
			}
		}

		/**
		 * Update post with the specified post data
		 *
		 * @param array $post_data The post data to update the post with
		 *
		 * @return void
		 */
		public static function update_posts( $request_data ) {
			if ( ! is_array( $request_data['posts'] ) || ! $request_data['posts'] ) {
				wp_send_json_error( [ 'message' => 'No posts in request' ] );
				wp_die();
			}
			$posts_updated    = 0;
			$posts_permalinks = [];

			foreach ( $request_data['posts'] as $post_data ) {
				$existing_posts = self::get_post_by_docswrite_id( $post_data['id'] );
				
				foreach ( $existing_posts as $existing_post ) {					
						$post_id = $existing_post->ID;

						wp_update_post( array(
							'ID'            => $post_id,
							'tags_input'    => $post_data['tags'],
							'post_title'    => sanitize_text_field( $post_data['title'] ),
							'post_name'     => sanitize_title_with_dashes( $post_data['slug'] ),
							'post_status'   => sanitize_text_field( $post_data['state'] ),
							'post_author'   => (int) $post_data['author'], // it must be an INT
							'post_date'     => date( 'Y-m-d H:i:s', strtotime( $post_data['date'] ) ),
							'post_excerpt'  => sanitize_text_field( $post_data['excerpt'] ),
							'post_type'     => sanitize_text_field( $post_data['post_type'] ),
							'post_category' => $post_data['categories'],
							'post_content'  => wp_kses_post( $post_data['content'] ),
						) );

						update_post_meta( $post_id, Docswrite::DOCSWRITE_POST_RAW_META_KEY, $post_data );

						self::set_image_to_post( $post_data['featured_image_url'], $post_id, $post_data['featured_image_alt_text'], $post_data['featured_image_caption'] );
						if ( $post_data['yoast_settings'] ) {
							$post_data['yoast_settings_clean'] = [];
							foreach ( $post_data['yoast_settings'] as $tmp_key => $tmp_value ) {
								$post_data['yoast_settings_clean'][ str_replace( 'yoast_', '', $tmp_key ) ] = $tmp_value;
							}
							self::setup_metadata( '_yoast_wpseo_', $post_data['yoast_settings_clean'], $post_id );
						}
						if ( $post_data['rankmath_settings'] ) {
							self::setup_metadata( '', $post_data['rankmath_settings'], $post_id );
						}
						if ( $post_data['newspack_settings'] ) {
							self::setup_metadata( '', $post_data['newspack_settings'], $post_id );
						}
						// Add permalink for $post_id to $posts_permalinks[]
						$posts_permalinks[ $post_id ] = get_permalink( $post_id );
						$posts_updated ++;
				}
			}

			if ( $posts_updated ) {
				wp_send_json_success( [
					'message'          => 'Posts updated: ' . $posts_updated,
					'posts_permalinks' => $posts_permalinks,
				] );
			} else {
				wp_send_json_error( [
					'message' => 'No posts updated'
				] );
			}

			wp_die();
		}

		/**
		 * Deletes posts based on the given request data
		 *
		 * @param array $request_data The request data containing the posts to delete
		 *
		 * @return void
		 */
		public static function delete_posts( $request_data ) {
			if ( ! is_array( $request_data['posts'] ) || ! $request_data['posts'] ) {
				wp_send_json_error( [ 'message' => 'No posts in request' ] );
				wp_die();
			}
			$posts_deleted = 0;
			foreach ( $request_data['posts'] as $post_data ) {
				$existing_posts = self::get_post_by_docswrite_id( $post_data['id'] );
				foreach ( $existing_posts as $existing_post ) {
					if ( is_object( $existing_post ) && isset( $existing_post->ID ) ) {
						// Remove post
						if ( wp_delete_post( $existing_post->ID, true ) ) {
							$posts_deleted ++;
						}
					}
				}
			}

			if ( $posts_deleted ) {
				wp_send_json_success( [
					'message' => 'Posts deleted: ' . $posts_deleted
				] );
			} else {
				wp_send_json_error( [
					'message' => 'No posts deleted'
				] );
			}

			wp_die();
		}

		/**
		 * Get authors
		 *
		 * This method retrieves a list of authors from the database and returns them in a JSON response.
		 *
		 * @return void
		 */
		public static function get_authors( $request_data ) {
			$search = isset($request_data['search']) ? sanitize_text_field($request_data['search']) : '';

			// Setup arguments
			$args = array(
				'orderby' => 'display_name',
			);
		
			// Add search parameter if provided
			if ( ! empty( $search ) ) {
				$args['search'] = '*' . $search . '*';
				$args['search_columns'] = array( 'display_name' );
			}

			// Create the WP_User_Query object
			$user_query = new WP_User_Query( $args );

			// Get the list of authors
			$authors = $user_query->get_results();

			// Initialize empty array
			$authordata = array();

			// Check if authors were found
			if ( ! empty( $authors ) ) {
				// Loop through each author
				foreach ( $authors as $author ) {
					// Add author data to array
					$authordata[] = array(
						'id'   => $author->ID,
						'name' => "{$author->data->display_name}",
					);
				}
			}

			if ( $authordata ) {
				wp_send_json_success( [
					'message' => 'Authors found: ' . count( $authordata ),
					'authors' => $authordata
				] );
			} else {
				wp_send_json_error( [
					'message' => 'No authors found'
				] );
			}

			wp_die();
		}

		/**
		 * Get all categories and filter based on search word
		 *
		 * @param $request_data
		 * @return void
		 */
		public static function get_categories( $request_data ) {
			$search = isset($request_data['search']) ? sanitize_text_field($request_data['search']) : '';
			$hide_empty = isset($request_data['hide_empty']) && $request_data['hide_empty'] === 'true';
	
			$args = array(
				'hide_empty' => $hide_empty,
			);
	
			if (!empty($search)) {
				$args['search'] = $search;
			}
	
			$categories = get_categories($args);
			$categories_array = array();
	
			foreach ($categories as $category) {
				$categories_array[] = array(
					'id' => $category->term_id,
					'name' => $category->name,
					'slug' => $category->slug,
					'description' => $category->description,
					'count' => $category->count,
				);
			}
	
			if (empty($categories_array)) {
				wp_send_json_error(['message' => 'No categories found']);
			} else {
				wp_send_json_success([
					'message'    => 'Categories retrieved successfully',
					'categories' => $categories_array,
				]);
			}
	
			wp_die();
		}
	
		/**
		 * Get all tags and filter based on search word
		 *
		 * @param $request_data
		 * @return void
		 */
		public static function get_tags( $request_data ) {
			$search = isset($request_data['search']) ? sanitize_text_field($request_data['search']) : '';
			$hide_empty = isset($request_data['hide_empty']) && $request_data['hide_empty'] === 'true';

			$args = array(
				'hide_empty' => $hide_empty,
			);

			if (!empty($search)) {
				$args['search'] = $search;
			}

			$tags = get_tags($args);
			$tags_array = array();

			foreach ($tags as $tag) {
				$tags_array[] = array(
					'id' => $tag->term_id,
					'name' => $tag->name,
					'slug' => $tag->slug,
					'description' => $tag->description,
					'count' => $tag->count,
				);
			}

			if (empty($tags_array)) {
				wp_send_json_error(['message' => 'No tags found']);
			} else {
				wp_send_json_success([
					'message'    => 'Tags retrieved successfully',
					'tags' => $tags_array,
				]);
			}

			wp_die();
		}

		/**
		 * Get all taxonomies and their terms
		 *
		 * @return void
		 */
		public static function get_taxonomies_terms() {
			// Get all taxonomies
			$taxonomies    = get_taxonomies( '', 'objects' );
			$taxonomy_data = array();

			// Check if taxonomies were found
			if ( ! empty( $taxonomies ) ) {
				// Loop through each taxonomy
				foreach ( $taxonomies as $taxonomy ) {
					// Get terms for the taxonomy
					$terms = get_terms( array(
						'taxonomy'   => $taxonomy->name,
						'hide_empty' => false
					) );
					// Initialize empty array for this taxonomy
					$term_data = array();

					if ( ! empty( $terms ) ) {
						// Loop through each term
						foreach ( $terms as $term ) {
							// Add term data to array
							$term_data[] = array(
								'ID'   => $term->term_id,
								'name' => $term->name,
								'slug' => $term->slug,
							);
						}
					}

					// Add term data to taxonomy_data under the taxonomy name
					$taxonomy_data[ $taxonomy->label ] = $term_data;
				}
			}

			if ( $taxonomy_data ) {
				wp_send_json_success( [
					'message'    => 'Taxonomies and terms found',
					'taxonomies' => $taxonomy_data
				] );
			} else {
				wp_send_json_error( [
					'message' => 'No taxonomies or terms found'
				] );
			}

			wp_die();
		}
	}
}