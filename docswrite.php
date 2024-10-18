<?php
/**
 * Plugin Name: Docswrite – Export Google Docs to Your Site ✨
 * Plugin URI: https://docswrite.com/
 * Description: Official Docswrite Integration. Google Docs to WordPress in One-Click. Save 100s of hours every month. No more copy-pasting. No more formatting issues.
 * Version: 1.2.2
 * Tested up to: 6.7
 * Requires PHP: 5.3
 * Author: Docswrite
 * Text Domain: docswrite
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Stable tag: 1.2.1
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

require_once __DIR__ . '/DocswriteAPI.php';

if ( ! class_exists( 'Docswrite' ) ) {
    class Docswrite {
        const DOCSWRITE_CONNECTION_ENDPOINT = 'https://docswrite.com/dashboard/integrations/wordpress_plugin?uuid={website_id}&name={name}&url={url}';
        const DOCSWRITE_CONNECTION_OPTION = 'docswrite_connection';
        const DOCSWRITE_WEBSITE_ID_OPTION = 'docswrite_website_id';
        const DOCSWRITE_POST_RAW_META_KEY = 'docswrite_raw_post_object';
        const DOCSWRITE_POST_ID_META_KEY = 'docswrite_post_id';

        /**
         * Initializes the Docswrite plugin by setting up the necessary actions.
         * @return void
         */
        public static function init() {
            add_action('admin_menu', array('Docswrite', 'docswrite_settings_menu'));
            add_action('init', array('Docswrite', 'handle_disconnection'));
            add_action('admin_enqueue_scripts', array('Docswrite', 'enqueue_admin_assets'));

            /**
             * Register REST endpoints
             */
            DocswriteAPI::register_api_endpoints();
            add_action('rest_api_init', array('Docswrite', 'register_rest_routes'));
        }

        /**
         * Handles disconnection request from the user.
         */
        public static function handle_disconnection() {
            if (isset($_POST['disconnect']) && $_POST['disconnect'] === '1') {
                // Verify nonce
                if (!isset($_POST['docswrite_disconnect_nonce']) || !wp_verify_nonce(wp_unslash($_POST['docswrite_disconnect_nonce']), 'docswrite_disconnect_action')) {
                    wp_die('Security check failed', 'Security Error', array('response' => 403));
                }
                update_option(self::DOCSWRITE_CONNECTION_OPTION, 0);
                wp_safe_redirect(admin_url('admin.php?page=docswrite'));
                exit;
            }
        }

        /**
         * Adds the Docswrite Settings menu page to the WordPress admin menu.
         */
        public static function docswrite_settings_menu() {
            add_menu_page(
                __('Docswrite Settings', 'docswrite'),
                __('Docswrite', 'docswrite'),
                'manage_options',
                'docswrite',
                array('Docswrite', 'docswrite_settings_page'),
                plugin_dir_url(__FILE__) . 'assets/logos/docswrite-logo.png',
                80
            );
        }

        /**
         * Renders the Docswrite settings page.
         */
        public static function docswrite_settings_page() {
            $website_id    = self::get_website_id();
            $website_name  = self::get_website_name();
            $website_url   = self::get_website_url();
            $connection    = self::is_connected() ? 'Connected' : 'Disconnected';

            $connection_url = $connection === 'Disconnected' 
                ? str_replace(
                    ['{website_id}', '{name}', '{url}'], 
                    [$website_id, urlencode($website_name), urlencode($website_url)], 
                    self::DOCSWRITE_CONNECTION_ENDPOINT
                ) 
                : '#';
            ?>
            <img src="<?php echo esc_url(plugin_dir_url(__FILE__) . 'assets/logos/docswrite-logo.svg'); ?>" alt="Docswrite Logo" class="docswrite-logo">
            <h2><?php esc_html_e('Docswrite Settings', 'docswrite'); ?></h2>
            <p><?php esc_html_e('These settings are only applicable if you have connected your website to Docswrite through this plugin.', 'docswrite'); ?></p>
            <form action="<?php echo esc_url($connection_url); ?>" method="post" target="_blank">
                <p>
                    <label for="website-id"><?php esc_html_e('Website ID', 'docswrite'); ?></label> <br/> <input type="text" id="website-id" name="website-id" size="32" value="<?php echo esc_attr($website_id); ?>" readonly>
                </p>
                <p>
                    <label for="connection-status"><?php esc_html_e('Connection', 'docswrite'); ?></label> <br/> <span style="color: <?php echo $connection === 'Disconnected' ? 'darkred' : 'darkgreen'; ?>; font-weight: bold"><?php echo esc_html($connection); ?></span>
                </p>
                <?php if ($connection === 'Connected') { ?>
                    <input type="hidden" name="disconnect" value="1">
                    <?php wp_nonce_field('docswrite_disconnect_action', 'docswrite_disconnect_nonce'); ?>
                <?php }
                // Output submit button
                if ($connection === 'Connected') {
                    submit_button(esc_html__('Disconnect', 'docswrite'), 'primary', 'connection-button', false, array('onclick' => 'return confirm_disconnect()' ));
                } else {
                    submit_button(esc_html__('Connect', 'docswrite'), 'primary', 'connection-button');
                }
                ?>
            </form>
            <?php
        }

        /**
         * Gets the unique website ID for the current site.
         */
        public static function get_website_id() {
            return get_option(self::DOCSWRITE_WEBSITE_ID_OPTION);
        }

        /**
         * Gets the website URL for the current site.
         */
        public static function get_website_url() {
            return get_bloginfo('url');
        }

        /**
         * Gets the website name for the current site.
         */
        public static function get_website_name() {
            $name = get_bloginfo('name');
            
            // Check if the name is empty
            if (empty($name)) {
                // Array of elements to create an infinite variety of names
                $adjectives = array(
                    'Eternal', 'Boundless', 'Infinite', 'Endless', 'Timeless',
                    'Limitless', 'Celestial', 'Everlasting', 'Mystic', 'Galactic',
                    'Stellar', 'Luminous', 'Ethereal', 'Quantum', 'Radiant',
                    'Majestic', 'Serene', 'Vibrant', 'Enigmatic', 'Harmonic'
                );

                $nouns = array(
                    'Horizons', 'Visions', 'Dreams', 'Realms', 'Adventures',
                    'Journeys', 'Expanses', 'Odysseys', 'Chronicles', 'Legends',
                    'Phenomena', 'Sagas', 'Echoes', 'Mysteries', 'Voyages',
                    'Frontiers', 'Dimensions', 'Worlds', 'Galaxies'
                );
        
                // Randomly select one element from each array
                $random_adjective = $adjectives[array_rand($adjectives)];
                $random_noun = $nouns[array_rand($nouns)];
                
                // Construct the random blog name
                $name = $random_adjective . ' ' . $random_noun;
            }
            
            return $name;
        }

        /**
         * Generate Website ID
         */
        private static function generate_website_id() {
            return md5(get_site_url() . wp_generate_uuid4());
        }

        /**
         * Activates the Docswrite plugin by checking and setting the necessary options.
         */
        public static function activate() {
            // If website ID option is empty, set value.
            if (!self::get_website_id()) {
                update_option(self::DOCSWRITE_WEBSITE_ID_OPTION, self::generate_website_id());
            }
        }

        /**
         * Checks if the Docswrite connection is active.
         */
        public static function is_connected() {
            return (bool) get_option(self::DOCSWRITE_CONNECTION_OPTION);
        }

        /**
         * Registers custom REST API routes.
         */
        public static function register_rest_routes() {
            register_rest_route('docswrite/v1', '/docswrite', array(
                'methods' => 'GET',
                'callback' => array('Docswrite', 'rest_callback'),
            ));

            register_rest_route('docswrite/v1', '/update-rank-math', array(
                'methods' => 'POST',
                'callback' => array('Docswrite', 'update_rankmath'),
                'permission_callback' => array('Docswrite', 'permission_check'),
            ));

            register_rest_route('docswrite/v1', '/update-yoast', array(
                'methods' => 'POST',
                'callback' => array('Docswrite', 'update_yoast'),
                'permission_callback' => array('Docswrite', 'permission_check'),
            ));
        }

        /**
         * Callback for the sample GET endpoint.
         */
        public static function rest_callback() {
            return array(
                'message' => 'Hello from the Docswrite REST API endpoint!',
            );
        }

        /**
         * Checks if the current user has permission to modify posts.
         */
        public static function permission_check() {
            if (current_user_can('edit_posts')) {
                return true;
            }
            return new WP_Error('rest_forbidden', 'You do not have permission to modify posts.', array('status' => 401));
        }

        /**
         * Updates Rank Math focus keyword.
         */
        public static function update_rankmath($request) {
            if (!function_exists('rank_math')) {
                return new WP_Error('rank_math_not_installed', 'The Rank Math plugin is not installed.', array('status' => 500));
            }

            $focus_keyword = $request->get_param('rank_math_focus_keyword');
            $post_id = $request->get_param('post_id');

            if (empty($focus_keyword)) {
                return new WP_Error('rank_math_focus_keyword_empty', 'The rank_math_focus_keyword is empty.', array('status' => 500));
            }

            if (empty($post_id)) {
                return new WP_Error('post_id_empty', 'The post_id is empty.', array('status' => 500));
            }

            update_post_meta($post_id, 'rank_math_focus_keyword', $focus_keyword);

            return array(
                'message' => 'The focus keyword has been updated.',
            );
        }

        /**
         * Updates Yoast SEO fields.
         */
        public static function update_yoast($request) {
            if (!class_exists('WPSEO_Options')) {
                return new WP_Error('yoast_not_installed', 'The Yoast plugin is not installed.', array('status' => 500));
            }

            $post_id = $request->get_param('post_id');
            $focuskw = $request->get_param('yoast_wpseo_focuskw');
            $title = $request->get_param('yoast_wpseo_title');
            $metadesc = $request->get_param('yoast_wpseo_metadesc');
            $canonical = $request->get_param('yoast_wpseo_canonical');

            $args = array(
                'ID' => $post_id,
                'meta_input' => array(
                    '_yoast_wpseo_focuskw' => $focuskw,
                    '_yoast_wpseo_title' => $title,
                    '_yoast_wpseo_metadesc' => $metadesc,
                    '_yoast_wpseo_canonical' => $canonical,
                ),
            );

            $result = wp_update_post($args);

            if (is_wp_error($result)) {
                return new WP_Error('update_failed', __('Failed to update Yoast SEO fields.', 'docswrite'), array('status' => 500));
            }

            return array('success' => true);
        }

        public static function enqueue_admin_assets($hook) {
            // Only enqueue on the Docswrite settings page
            if ('toplevel_page_docswrite' !== $hook) {
                return;
            }

            // Enqueue CSS
            wp_enqueue_style('docswrite-admin-style', plugin_dir_url(__FILE__) . 'assets/css/admin-style.css', array(), '1.0.0');

            // Add inline CSS
            $custom_css = "
                .docswrite-logo {
                    vertical-align: middle;
                    height: 32px;
                    margin-top: 1em;
                }
            ";
            wp_add_inline_style('docswrite-admin-style', $custom_css);

            // Enqueue JavaScript
            wp_enqueue_script('docswrite-admin-script', plugin_dir_url(__FILE__) . 'assets/js/admin-script.js', array('jquery'), '1.0.0', true);
            wp_localize_script('docswrite-admin-script', 'docswriteData', array(
                'disconnectConfirmMessage' => __('Do you really want to disconnect the website? Your content synchronization will be stopped.', 'docswrite'),
                'disconnectButtonText' => __('Disconnect', 'docswrite')
            ));
        }
    }

    Docswrite::init();
}

/**
 * Set website_id when plugin is activated
 */
register_activation_hook(__FILE__, array('Docswrite', 'activate'));
