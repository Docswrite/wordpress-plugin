<?php
/**
 * Plugin Name: Docswrite
 * Plugin URI: https://docswrite.com/
 * Description: Official Docswrite Integration. Google Docs to WordPress in 1-Click. Save 100s of hours every month. No more copy-pasting. No more formatting issues.
 * Version: 0.1
 * Author: Docswrite
 * Text Domain: docswrite
 */

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
         */
        public static function init() {
            add_action( 'admin_menu', array( 'Docswrite', 'docswrite_settings_menu' ) );
            add_action( 'init', array( 'Docswrite', 'handle_disconnection' ) );

            /**
             * Register REST endpoints
             */
            DocswriteAPI::register_api_endpoints();
        }

        /**
         * Handles disconnection request from the user.
         */
        public static function handle_disconnection() {
            if ( isset( $_POST['disconnect'] ) && $_POST['disconnect'] === '1' ) {
                update_option( self::DOCSWRITE_CONNECTION_OPTION, 0 );
                wp_safe_redirect( admin_url( 'admin.php?page=docswrite' ) );
                exit;
            }
        }

        /**
         * Adds the Docswrite Settings menu page to the WordPress admin menu.
         */
        public static function docswrite_settings_menu() {
            add_menu_page(
                __( 'Docswrite Settings', 'docswrite' ),
                __( 'Docswrite', 'docswrite' ),
                'manage_options',
                'docswrite',
                array( 'Docswrite', 'docswrite_settings_page' ),
        		plugin_dir_url( __FILE__ ) . 'assets/logos/docswrite-logo.png', 
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
            <img src="https://docswrite.com/full-logo.png" alt="Docswrite Favicon" style="vertical-align: middle; height: 32px; margin-top: 1em"><br/>
            <h2><?php _e( 'Docswrite Settings', 'docswrite' ); ?></h2>
            <form action="<?php echo esc_url( $connection_url ); ?>" method="post" target="_blank">
                <p>
                    <label for="website-id"><?php _e( 'Website ID', 'docswrite' ); ?></label> <br/> <input type="text" id="website-id" name="website-id" size="32" value="<?php echo esc_attr( $website_id ); ?>" readonly>
                </p>
                <p>
                    <label for="connection-status"><?php _e( 'Connection', 'docswrite' ); ?></label> <br/> <span style="color: <?php echo $connection === 'Disconnected' ? 'darkred' : 'darkgreen'; ?>; font-weight: bold"><?php echo esc_html( $connection ); ?></span>
                </p>
                <?php if ( $connection === 'Connected' ) { ?>
                    <input type="hidden" name="disconnect" value="1">
                <?php }
                // Output submit button
                if ( $connection === 'Connected' ) {
                    submit_button( __( 'Disconnect', 'docswrite' ), 'primary', 'connection-button', false, array( 'onclick' => 'return confirm_disconnect()' ) );
                } else {
                    submit_button( __( 'Connect', 'docswrite' ), 'primary', 'connection-button' );
                }
                ?>
            </form>
            <script type="text/javascript">
                function confirm_disconnect() {
                    var connection_button = document.getElementById('connection-button');
                    if (connection_button.value === __('Disconnect', 'docswrite')) {
                        return confirm('<?php _e( 'Do you really want to disconnect the website? Your content synchronization will be stopped.', 'docswrite' ); ?>');
                    }

                    return true;
                }
            </script>
            <?php
        }

        /**
         * Gets the unique website ID for the current site.
         */
        public static function get_website_id() {
            return get_option( self::DOCSWRITE_WEBSITE_ID_OPTION );
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
            return md5( get_site_url() . wp_generate_uuid4() );
        }

        /**
         * Activates the Docswrite plugin by checking and setting the necessary options.
         */
        public static function activate() {
            // If website ID option is empty, set value.
            if ( ! self::get_website_id() ) {
                update_option( self::DOCSWRITE_WEBSITE_ID_OPTION, self::generate_website_id() );
            }
        }

        /**
         * Checks if the Docswrite connection is active.
         */
        public static function is_connected() {
            return (bool) get_option( self::DOCSWRITE_CONNECTION_OPTION );
        }
    }

    Docswrite::init();
}

/**
 * Set website_id when plugin is activated
 */
register_activation_hook( __FILE__, array( 'Docswrite', 'activate' ) );
?>
