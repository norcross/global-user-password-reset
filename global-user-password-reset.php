<?php
/**
 * Plugin Name: Global User Password Reset
 * Plugin URI: http://andrewnorcross.com/plugins/
 * Description: Reset all passwords on a site, excluding the current user.
 * Author: Andrew Norcross
 * Author URI: http://andrewnorcross.com/
 * Version: 0.0.2
 * Text Domain: global-user-password-reset
 * Requires WP: 4.0
 * Domain Path: languages
 * License: MIT - http://norcross.mit-license.org
 * GitHub Plugin URI: https://github.com/norcross/global-user-password-reset
 */

// Define our base file.
if( ! defined( 'GUPR_BASE' ) ) {
	define( 'GUPR_BASE', plugin_basename( __FILE__ ) );
}

// Define our base directory.
if ( ! defined( 'GUPR_DIR' ) ) {
	define( 'GUPR_DIR', plugin_dir_path( __FILE__ ) );
}

// Define our version.
if( ! defined( 'GUPR_VER' ) ) {
	define( 'GUPR_VER', '0.0.2' );
}


/**
 * Call our class.
 */
class GlobalUserReset_Base
{
	/**
	 * Static property to hold our singleton instance.
	 * @var $instance
	 */
	static $instance = false;

	/**
	 * This is our constructor. There are many like it, but this one is mine.
	 */
	private function __construct() {
		add_action( 'plugins_loaded',               array( $this, 'textdomain'          )           );
		add_action( 'plugins_loaded',               array( $this, 'load_files'          )           );
	}

	/**
	 * If an instance exists, this returns it.  If not, it creates one and
	 * retuns it.
	 *
	 * @return $instance
	 */
	public static function getInstance() {

		if ( ! self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Load our textdomain for localization.
	 *
	 * @return void
	 */
	public function textdomain() {
		load_plugin_textdomain( 'global-user-password-reset', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Load our actual files in the places they belong.
	 *
	 * @return void
	 */
	public function load_files() {

		// Load our admin-related functions.
		if ( is_admin() ) {
			require_once( GUPR_DIR . 'lib/admin.php' );
		}
	}

	// End our class.
}

// Instantiate our class.
$GlobalUserReset_Base = GlobalUserReset_Base::getInstance();
