<?php
/**
 * Checkout Files Upload - General Section Settings
 *
 * @version 1.4.4
 * @since   1.0.0
 * @author  Algoritmika Ltd.
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'Alg_WC_Checkout_Files_Upload_Settings_General' ) ) :

class Alg_WC_Checkout_Files_Upload_Settings_General extends Alg_WC_Checkout_Files_Upload_Settings_Section {

	/**
	 * Constructor.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	function __construct() {
		$this->id   = '';
		$this->desc = __( 'General', 'checkout-files-upload-woocommerce' );
		parent::__construct();
	}

	/**
	 * get_settings.
	 *
	 * @version 1.4.4
	 * @since   1.0.0
	 * @todo    [dev] maybe make "AJAX form" enabled by default
	 */
	function get_settings() {
		$settings = array(
			array(
				'title'    => __( 'Checkout Files Upload Options', 'checkout-files-upload-woocommerce' ),
				'type'     => 'title',
				'desc'     => __( 'Let your customers upload files on (or after) WooCommerce checkout.', 'checkout-files-upload-woocommerce' ),
				'id'       => 'alg_checkout_files_upload_general_options',
			),
			array(
				'title'    => __( 'WooCommerce Checkout Files Upload', 'checkout-files-upload-woocommerce' ),
				'desc'     => '<strong>' . __( 'Enable plugin', 'checkout-files-upload-woocommerce' ) . '</strong>',
				'desc_tip' => __( 'Checkout Files Upload for WooCommerce.', 'checkout-files-upload-woocommerce' ),
				'id'       => 'alg_wc_checkout_files_upload_enabled',
				'default'  => 'yes',
				'type'     => 'checkbox',
			),
			array(
				'title'    => __( 'Total files', 'checkout-files-upload-woocommerce' ),
				'id'       => 'alg_checkout_files_upload_total_number',
				'desc_tip' => __( '<em>Save changes</em>, after you change this number, to see new settings sections.', 'checkout-files-upload-woocommerce' ),
				'default'  => 1,
				'type'     => 'number',
				'desc'     => apply_filters( 'alg_wc_checkout_files_upload_option', '<br>' . sprintf(
					__( 'You will need %s plugin to add more than one file.', 'checkout-files-upload-woocommerce' ),
					'<a target="_blank" href="' . esc_url( 'https://wpwham.com/products/checkout-files-upload-for-woocommerce/' ) . '">' .
						__( 'Checkout Files Upload for WooCommerce Pro', 'checkout-files-upload-woocommerce' ) . '</a>'
					), 'settings_total_files_button' ),
				'custom_attributes' => apply_filters( 'alg_wc_checkout_files_upload_option', array( 'readonly' => 'readonly' ), 'settings_total_files' ),
			),
			array(
				'title'    => __( 'AJAX form', 'checkout-files-upload-woocommerce' ),
				'desc'     => __( 'Enable', 'checkout-files-upload-woocommerce' ),
				'id'       => 'alg_checkout_files_upload_use_ajax',
				'default'  => 'yes',
				'type'     => 'checkbox',
				'show_if_checked' => 'option',
				'checkboxgroup'   => 'start',
			),
			array(
				'desc'     => __( 'Add progress bar', 'checkout-files-upload-woocommerce' ),
				'id'       => 'alg_checkout_files_upload_use_ajax_progress_bar',
				'default'  => 'no',
				'type'     => 'checkbox',
				'show_if_checked' => 'yes',
				'checkboxgroup'   => '',
			),
			array(
				'desc'     => __( 'Enable alert on successful file upload', 'checkout-files-upload-woocommerce' ),
				'id'       => 'alg_checkout_files_upload_use_ajax_alert_success_upload',
				'default'  => 'no',
				'type'     => 'checkbox',
				'show_if_checked' => 'yes',
				'checkboxgroup'   => '',
			),
			array(
				'desc'     => __( 'Enable alert on successful file remove', 'checkout-files-upload-woocommerce' ),
				'id'       => 'alg_checkout_files_upload_use_ajax_alert_success_remove',
				'default'  => 'no',
				'type'     => 'checkbox',
				'show_if_checked' => 'yes',
				'checkboxgroup'   => 'end',
			),
			array(
				'title'    => __( 'Max file size', 'checkout-files-upload-woocommerce' ),
				'desc'     => __( 'MB', 'checkout-files-upload-woocommerce' ),
				'desc_tip' => __( 'Leave zero to disable.', 'checkout-files-upload-woocommerce' ),
				'id'       => 'alg_checkout_files_upload_max_file_size_mb',
				'default'  => 0,
				'type'     => 'number',
				'custom_attributes' => array( 'min' => 0, 'step' => 0.001 ),
			),
			array(
				'desc_tip' => __( 'Message on exceeded. Replaced value: %max_file_size%.', 'checkout-files-upload-woocommerce' ),
				'id'       => 'alg_checkout_files_upload_max_file_size_exceeded_message',
				'default'  => __( 'Allowed file size exceeded (maximum %max_file_size% MB).', 'checkout-files-upload-woocommerce' ),
				'type'     => 'textarea',
				'css'      => 'width:100%',
				'alg_wc_cfu_raw' => true,
			),
			array(
				'type'     => 'sectionend',
				'id'       => 'alg_checkout_files_upload_general_options',
			),
		);
		return $settings;
	}

}

endif;

return new Alg_WC_Checkout_Files_Upload_Settings_General();
