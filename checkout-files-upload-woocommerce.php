<?php
/*
Plugin Name: Checkout Files Upload for WooCommerce
Plugin URI: https://wpwham.com/products/checkout-files-upload-for-woocommerce/
Description: Let your customers upload files on (or after) WooCommerce checkout.
Version: 2.0.1
Author: WP Wham
Author URI: https://wpwham.com
Text Domain: checkout-files-upload-woocommerce
Domain Path: /langs
Copyright: © 2018-2020 WP Wham
WC tested up to: 4.2
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

// Check if WooCommerce is active
$plugin = 'woocommerce/woocommerce.php';
if (
	! in_array( $plugin, apply_filters( 'active_plugins', get_option( 'active_plugins', array() ) ) ) &&
	! ( is_multisite() && array_key_exists( $plugin, get_site_option( 'active_sitewide_plugins', array() ) ) )
) {
	return;
}

if ( 'checkout-files-upload-woocommerce.php' === basename( __FILE__ ) ) {
	// Check if Pro is active, if so then return
	$plugin = 'checkout-files-upload-woocommerce-pro/checkout-files-upload-woocommerce-pro.php';
	if (
		in_array( $plugin, apply_filters( 'active_plugins', get_option( 'active_plugins', array() ) ) ) ||
		( is_multisite() && array_key_exists( $plugin, get_site_option( 'active_sitewide_plugins', array() ) ) )
	) {
		return;
	}
}

if ( ! defined( 'WPWHAM_CHECKOUT_FILES_UPLOAD_VERSION' ) ) {
	define( 'WPWHAM_CHECKOUT_FILES_UPLOAD_VERSION', '2.0.1' );
}
if ( ! defined( 'WPWHAM_CHECKOUT_FILES_UPLOAD_DBVERSION' ) ) {
	define( 'WPWHAM_CHECKOUT_FILES_UPLOAD_DBVERSION', '2' );
}
if ( ! defined( 'WPWHAM_CHECKOUT_FILES_UPLOAD_PATH' ) ) {
	define( 'WPWHAM_CHECKOUT_FILES_UPLOAD_PATH', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'WPWHAM_CHECKOUT_FILES_UPLOAD_FILE' ) ) {
	define( 'WPWHAM_CHECKOUT_FILES_UPLOAD_FILE', __FILE__ );
}

/**
 * Update scripts
 */
require_once( plugin_dir_path( __FILE__ ) . 'includes/checkout-files-upload-woocommerce-update.php' );

if ( ! class_exists( 'Alg_WC_Checkout_Files_Upload' ) ) :

/**
 * Main Alg_WC_Checkout_Files_Upload Class
 *
 * @class   Alg_WC_Checkout_Files_Upload
 * @version 2.0.0
 * @since   1.0.0
 */
final class Alg_WC_Checkout_Files_Upload {

	/**
	 * Plugin version.
	 *
	 * @var   string
	 * @since 1.0.0
	 */
	public $version = '2.0.1';

	/**
	 * @var   Alg_WC_Checkout_Files_Upload The single instance of the class
	 * @since 1.0.0
	 */
	protected static $_instance = null;

	/**
	 * Main Alg_WC_Checkout_Files_Upload Instance
	 *
	 * Ensures only one instance of Alg_WC_Checkout_Files_Upload is loaded or can be loaded.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 * @static
	 * @return  Alg_WC_Checkout_Files_Upload - Main instance
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Alg_WC_Checkout_Files_Upload Constructor.
	 *
	 * @version 1.4.0
	 * @since   1.0.0
	 * @access  public
	 */
	function __construct() {

		// Set up localisation
		load_plugin_textdomain( 'checkout-files-upload-woocommerce', false, dirname( plugin_basename( __FILE__ ) ) . '/langs/' );

		// Include required files
		$this->includes();

		// Admin
		if ( is_admin() ) {
			add_filter( 'woocommerce_get_settings_pages', array( $this, 'add_woocommerce_settings_tab' ) );
			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'action_links' ) );
			// Settings
			require_once( 'includes/settings/class-alg-wc-checkout-files-upload-settings-section.php' );
			require_once( 'includes/settings/class-alg-wc-checkout-files-upload-settings-file.php' );
			$this->settings = array();
			$this->settings['general']  = require_once( 'includes/settings/class-alg-wc-checkout-files-upload-settings-general.php' );
			$this->settings['emails']   = require_once( 'includes/settings/class-alg-wc-checkout-files-upload-settings-emails.php' );
			$this->settings['template'] = require_once( 'includes/settings/class-alg-wc-checkout-files-upload-settings-template.php' );
			$total_number = apply_filters( 'alg_wc_checkout_files_upload_option', 1, 'total_number' );
			for ( $i = 1; $i <= $total_number; $i++ ) {
				$this->settings[ 'file_' . $i ]  = new Alg_WC_Checkout_Files_Upload_Settings_File( $i );
			}
			// Version updated
			if ( get_option( 'alg_checkout_files_upload_version', '' ) !== $this->version ) {
				add_action( 'admin_init', array( $this, 'version_updated' ) );
			}
		}

	}

	/**
	 * Show action links on the plugin screen.
	 *
	 * @version 1.4.0
	 * @since   1.0.0
	 * @param   mixed $links
	 * @return  array
	 */
	function action_links( $links ) {
		$custom_links = array();
		$custom_links[] = '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=alg_wc_checkout_files_upload' ) . '">' . __( 'Settings', 'woocommerce' ) . '</a>';
		if ( 'checkout-files-upload-woocommerce.php' === basename( __FILE__ ) ) {
			$custom_links[] = '<a target="_blank" href="https://wpwham.com/products/checkout-files-upload-for-woocommerce/">' .
				__( 'Unlock all', 'checkout-files-upload-woocommerce' ) . '</a>';
		}
		return array_merge( $custom_links, $links );
	}

	/**
	 * Include required core files used in admin and on the frontend.
	 *
	 * @version 1.4.0
	 * @since   1.0.0
	 */
	function includes() {
		// Functions
		require_once( 'includes/alg-wc-checkout-files-upload-functions.php' );
		// Core
		$this->core = require_once( 'includes/class-alg-wc-checkout-files-upload.php' );
	}

	/**
	 * version_updated.
	 *
	 * @version 1.4.0
	 * @since   1.4.0
	 */
	function version_updated() {
		foreach ( $this->settings as $section ) {
			foreach ( $section->get_settings() as $value ) {
				if ( isset( $value['default'] ) && isset( $value['id'] ) ) {
					$autoload = isset( $value['autoload'] ) ? ( bool ) $value['autoload'] : true;
					add_option( $value['id'], $value['default'], '', ( $autoload ? 'yes' : 'no' ) );
				}
			}
		}
		update_option( 'alg_checkout_files_upload_version', $this->version );
	}

	/**
	 * Add Checkout Files Upload settings tab to WooCommerce settings.
	 *
	 * @version 1.4.0
	 * @since   1.0.0
	 */
	function add_woocommerce_settings_tab( $settings ) {
		$settings[] = require_once( 'includes/settings/class-wc-settings-checkout-files-upload.php' );
		return $settings;
	}

	/**
	 * Get the plugin url.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 * @return string
	 */
	function plugin_url() {
		return untrailingslashit( plugin_dir_url( __FILE__ ) );
	}

	/**
	 * Get the plugin path.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 * @return string
	 */
	function plugin_path() {
		return untrailingslashit( plugin_dir_path( __FILE__ ) );
	}

}

endif;

if ( ! function_exists( 'alg_wc_checkout_files_upload' ) ) {
	/**
	 * Returns the main instance of Alg_WC_Checkout_Files_Upload to prevent the need to use globals.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 * @return  Alg_WC_Checkout_Files_Upload
	 */
	function alg_wc_checkout_files_upload() {
		return Alg_WC_Checkout_Files_Upload::instance();
	}
}

alg_wc_checkout_files_upload();
