<?php
/*
 * Plugin Name: Postal Code Locations
 * Plugin URI:  http://wordpress.org/plugins/postal-code-locations
 * Description: Allows users to search for content within a radius of a certain postal code by integrating with zippopotam.us.
 * Version:     1.0
 * Author:      Jonathan Desrosiers & Linchpin Agency
 * Author URI:  http://linchpin.agency/?utm_source=in-stock-layered-nav-for-woocommerce&utm_medium=plugin-admin-page&utm_campaign=wp-plugin
 * License:     GPL 2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

define( 'PCL_VERSION', '0.2' );
define( 'PCL_DATABASE_VERSION', get_option( 'pcl_version', '0.0' ) );
define( 'PCL_PLUGIN_URL', trailingslashit( plugins_url( '', __FILE__ ) ) );
define( 'PCL_PLUGIN_DIR_URL', plugin_dir_path( __FILE__ ) );

include( 'upgrades.php' );
include( 'includes/class-pcl-taxonomy.php' );
include( 'includes/class-pcl-settings.php' );
include( 'includes/class-pcl-meta-boxes.php' );
include( 'includes/class-postal-code.php' );
include( 'includes/pcl-functions.php' );

class Postal_Code_Locations {

	function __construct() {
		add_action( 'init', array( $this, 'init' ), 11 );

		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
	}

	function init() {
		$enabled_post_types = pcl_get_enabled_post_types();

		foreach ( $enabled_post_types as $post_type ) {
			add_post_type_support( $post_type, 'postal_code_locations'  );
		}
	}

	/**
	 *
	 */
	function admin_enqueue_scripts() {
		$current_screen = get_current_screen();

		if ( ! in_array( $current_screen->base, array( 'edit', 'post' ) ) ) {
			return;
		}

		if ( empty( $current_screen->post_type ) ) {
			return;
		}

		if ( ! pcl_has_support( $current_screen->post_type ) ) {
			return;
		}

		wp_enqueue_script( 'pcl-admin', PCL_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), PCL_VERSION, true );
	}
}
$postal_code_locations = new Postal_Code_Locations();