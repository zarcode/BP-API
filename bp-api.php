<?php
/*
Plugin Name: BP API
Plugin URI: https://github.com/zarcode/bp-api
Description: json API for BuddyPress. This plugin creates json api endpoints for https://github.com/WP-API
Author: zarcode, vapvarun
Version: 0.1
Author URI: https://github.com/zarcode/bp-api
*/


// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

if ( !class_exists( 'BuddyPress_API' ) ) :
	
	/**
	 * Main BuddyPress API Class
	 */
	class BuddyPress_API {

		/**
		 * Main BuddyPress API Instance.
		 */
		public static function instance() {

			// Store the instance locally to avoid private static replication
			static $instance = null;

			// Only run these methods if they haven't been run previously
			if ( null === $instance ) {
				$instance = new BuddyPress_API;
				$instance->constants();
				$instance->actions();
			}

			// Always return the instance
			return $instance;

		}


		/**
		 * A dummy constructor to prevent BuddyPress API from being loaded more than once.
		 *
		 */
		private function __construct() { /* Do nothing here */ }


		/**
		 * Bootstrap constants.
		 *
		 */
		private function constants() {

			// define plugin translation string
			if ( ! defined( 'BP_API_PLUGIN_SLUG' ) ) {
				define( 'BP_API_PLUGIN_SLUG', 'bp_api' );
			}

			// define api endpint prefix
			if ( ! defined( 'BP_API_SLUG' ) ) {
				define( 'BP_API_SLUG', 'bp' );
			}

			// Define a constant that can be checked to see if the component is installed or not.
			if ( ! defined( 'BP_API_IS_INSTALLED' ) ) {
				define( 'BP_API_IS_INSTALLED', 1 );
			}

			// Define a constant that will hold the current version number of the component
			// This can be useful if you need to run update scripts or do compatibility checks in the future
			if ( ! defined( 'BP_API_VERSION' ) ) {
				define( 'BP_API_VERSION', '0.1' );
			}

			// Define a constant that we can use to construct file paths and url
			if ( ! defined( 'BP_API_PLUGIN_DIR' ) ) {
				define( 'BP_API_PLUGIN_DIR', trailingslashit( plugin_dir_path( __FILE__ ) ) );
			}

			if ( ! defined( 'BP_API_PLUGIN_URL' ) ) {
				$plugin_url = plugin_dir_url( __FILE__ );

				// If we're using https, update the protocol. Workaround for WP13941, WP15928, WP19037.
				if ( is_ssl() )
					$plugin_url = str_replace( 'http://', 'https://', $plugin_url );

				define( 'BP_API_PLUGIN_URL', $plugin_url );
			}

		}


		/**
		 * BP-API Actions
		 * 
		 * Includes actions for init, activation/deactivation hooks, and admin notices.
		 *
		 */
		private function actions() {

			register_activation_hook( __FILE__, array( $this, 'bp_api_activate' ) );
			register_deactivation_hook( __FILE__, array( $this, 'bp_api_deactivate' ) );


			add_action( 'plugins_loaded', array( $this, 'check_if_exists' ), 9999 );
			add_action( 'bp_include', array( $this, 'bp_api_include' ) );
		}
		
		

		/**
		 * check_if_exists function.
		 *
		 * checks for plugin dependency and deactivates if not found
		 * 
		 * @access public
		 * @return void
		 */
		public function check_if_exists() {
		
			// is BuddyPress plugin active? If not, throw a notice and deactivate
			if ( !class_exists( 'BuddyPress' ) ) {
				add_action( 'all_admin_notices', array( $this, 'bp_api_buddypress_required' ) );
				return;
			}

			// is JSON API plugin active? If not, throw a notice and deactivate
			if ( !class_exists('WP_REST_Server') ) {
				add_action( 'all_admin_notices', array( $this, 'bp_api_wp_api_required' ) );
				return;
			}
			
		}


		/**
		 * bp_api_init function.
		 * 
		 * much files, so include
		 *
		 * @access public
		 * @return void
		 */
		public function bp_api_include() {

			// requires BP 2.0 or greater.
			if ( version_compare( BP_VERSION, '2.0', '>' ) ) {
				include_once( dirname( __FILE__ ) . '/endpoints/bp-api-core.php' );
				include_once( dirname( __FILE__ ) . '/endpoints/bp-api-activity.php' );
				include_once( dirname( __FILE__ ) . '/endpoints/bp-api-xprofile.php' );
                include_once( dirname( __FILE__ ) . '/endpoints/bp-api-message.php' );
			}

			add_action( 'rest_api_init', array( $this, 'bp_api_init' ), 0 );
		}


		/**
		 * bp_api_activate function.
		 *
		 * @access public
		 * @return void
		 */
		public function bp_api_activate() {
		}


		/**
		 * bp_api_deactivate function.
		 *
		 * @access public
		 * @return void
		 */
		public function bp_api_deactivate() {
		}


		/**
		 * bp_api_buddypress_required function.
		 *
		 * @access public
		 * @return void
		 */
		public function bp_api_buddypress_required() {
			echo '<div id="message" class="error"><p>'. sprintf( __( '%1$s requires the <a href="https://buddypress.org/">BuddyPress plugin</a> to be installed/activated. %1$s has been deactivated.', 'bp-api' ), 'BuddyPress API' ) .'</p></div>';
			deactivate_plugins( plugin_basename( __FILE__ ), true );
		}


		/**
		 * bp_api_wp_api_required function.
		 *
		 * @access public
		 * @return void
		 */
		public function bp_api_wp_api_required() {
			echo '<div id="message" class="error"><p>'. sprintf( __( '%1$s requires the <a href="https://github.com/WP-API/WP-API/releases/tag/2.0-beta3">WP API V2 plugin</a> to be installed/activated. %1$s has been deactivated.', 'bp-api' ), 'BuddyPress API' ) .'</p></div>';
			deactivate_plugins( plugin_basename( __FILE__ ), true );
		}



		/**
		 * create_bp_endpoints function.
		 *
		 * adds BuddyPress data endpoints to WP-API
		 * 
		 * @access public
		 * @return void
		 */
		public function bp_api_init() {

			/*
			* BP Core
			*/
			$bp_api_core = new BP_API_Core;
			$bp_api_core->register_routes();


			/*
			* BP Activity
			*/
			if ( bp_is_active( 'activity' ) ) {
				$bp_api_activity = new BP_API_Activity;
				$bp_api_activity->register_routes();
			}
            
			/*
			* BP Message
			*/
			if ( bp_is_active( 'messages' ) ) {
				$bp_api_messages = new BP_API_Messages;
				$bp_api_messages->register_routes();
			}

			/*
			* BP xProfile
			*/
			if ( bp_is_active( 'xprofile' ) ) {
				$bp_api_xprofile = new BP_API_xProfile;
				register_rest_route( BP_API_SLUG, '/xprofile', array(
					'methods'         => 'GET',
					'callback'        => array( $bp_api_xprofile, 'get_items' ),
				) );
				register_rest_route( BP_API_SLUG, '/xprofile/(?P<id>\d+)', array(
					'methods'         => 'GET',
					'callback'        => array( $bp_api_xprofile, 'get_item' ),
				) );
			}


		}


	}

endif;

function bp_api() {
	return BuddyPress_API::instance();
}
bp_api();