<?php
/**
 * Checkout Files Upload
 *
 * @version 1.4.5
 * @since   1.0.0
 * @author  Algoritmika Ltd.
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'Alg_WC_Checkout_Files_Upload_Main' ) ) :

class Alg_WC_Checkout_Files_Upload_Main {

	/**
	 * Constructor.
	 *
	 * @version 1.4.5
	 * @since   1.0.0
	 * @todo    [dev] split this file into smaller ones
	 * @todo    [feature] multiple files upload in single input
	 * @todo    [feature] max file size on per file basis
	 * @todo    [feature] hide fields by user role; by country etc.
	 */
	function __construct() {
		if ( 'yes' === get_option( 'alg_wc_checkout_files_upload_enabled', 'yes' ) ) {
			add_action( 'add_meta_boxes', array( $this, 'add_file_admin_order_meta_box' ) );
			add_action( 'init', array( $this, 'process_checkout_files_upload' ) );
			$total_number = apply_filters( 'alg_wc_checkout_files_upload_option', 1, 'total_number' );
			for ( $i = 1; $i <= $total_number; $i++ ) {
				if ( 'disable' != ( $the_hook = get_option( 'alg_checkout_files_upload_hook_' . $i, 'woocommerce_before_checkout_form' ) ) ) {
					add_action( $the_hook, array( $this, 'add_files_upload_form_to_checkout_frontend' ), get_option( 'alg_checkout_files_upload_hook_priority_' . $i, 20 ) );
				}
				if ( 'yes' === get_option( 'alg_checkout_files_upload_add_to_thankyou_' . $i, 'no' ) ) {
					add_action( 'woocommerce_thankyou',   array( $this, 'add_files_upload_form_to_thankyou_and_myaccount_page' ), PHP_INT_MAX, 1 );
				}
				if ( 'yes' === get_option( 'alg_checkout_files_upload_add_to_myaccount_' . $i, 'no' ) ) {
					add_action( 'woocommerce_view_order', array( $this, 'add_files_upload_form_to_thankyou_and_myaccount_page' ), PHP_INT_MAX, 1 );
				}
			}
			add_action( 'woocommerce_checkout_order_processed',        array( $this, 'add_files_to_order' ), PHP_INT_MAX, 2 );
			add_action( 'woocommerce_after_checkout_validation',       array( $this, 'validate_on_checkout' ) );
			add_action( 'woocommerce_order_details_after_order_table', array( $this, 'add_files_to_order_display' ), PHP_INT_MAX );
			add_action( 'woocommerce_email_after_order_table',         array( $this, 'add_files_to_order_display' ), PHP_INT_MAX );
			add_filter( 'woocommerce_email_attachments',               array( $this, 'add_files_to_email_attachments' ), PHP_INT_MAX, 3 );
			add_action( 'wp_enqueue_scripts',                          array( $this, 'enqueue_scripts' ), PHP_INT_MAX );
			if ( 'yes' === get_option( 'alg_checkout_files_upload_use_ajax', 'yes' ) ) {
				add_action( 'wp_ajax_'        . 'alg_ajax_file_upload',    array( $this, 'alg_ajax_file_upload' ) );
				add_action( 'wp_ajax_nopriv_' . 'alg_ajax_file_upload',    array( $this, 'alg_ajax_file_upload' ) );
				add_action( 'wp_ajax_'        . 'alg_ajax_file_delete',    array( $this, 'alg_ajax_file_delete' ) );
				add_action( 'wp_ajax_nopriv_' . 'alg_ajax_file_delete',    array( $this, 'alg_ajax_file_delete' ) );
			}
			add_shortcode( 'alg_wc_cfu_translate', array( $this, 'language_shortcode' ) );
		}
	}

	/**
	 * language_shortcode.
	 *
	 * @version 1.4.5
	 * @since   1.4.5
	 */
	function language_shortcode( $atts, $content = '' ) {
		// E.g.: `[alg_wc_cfu_translate lang="EN,DE" lang_text="Translation for EN and DE" not_lang_text="Translation for other languages"]`
		if ( isset( $atts['lang_text'] ) && isset( $atts['not_lang_text'] ) && ! empty( $atts['lang'] ) ) {
			return ( ! defined( 'ICL_LANGUAGE_CODE' ) || ! in_array( strtolower( ICL_LANGUAGE_CODE ), array_map( 'trim', explode( ',', strtolower( $atts['lang'] ) ) ) ) ) ?
				$atts['not_lang_text'] : $atts['lang_text'];
		}
		// E.g.: `[alg_wc_cfu_translate lang="EN,DE"]Translation for EN and DE[/alg_wc_cfu_translate][alg_wc_cfu_translate not_lang="EN,DE"]Translation for other languages[/alg_wc_cfu_translate]`
		return (
			( ! empty( $atts['lang'] )     && ( ! defined( 'ICL_LANGUAGE_CODE' ) || ! in_array( strtolower( ICL_LANGUAGE_CODE ), array_map( 'trim', explode( ',', strtolower( $atts['lang'] ) ) ) ) ) ) ||
			( ! empty( $atts['not_lang'] ) &&     defined( 'ICL_LANGUAGE_CODE' ) &&   in_array( strtolower( ICL_LANGUAGE_CODE ), array_map( 'trim', explode( ',', strtolower( $atts['not_lang'] ) ) ) ) )
		) ? '' : $content;
	}

	/**
	 * maybe_send_admin_notification.
	 *
	 * @version 1.4.4
	 * @since   1.4.4
	 * @todo    [feature] add "Email type" option (i.e. Plain text / HTML / Multipart)
	 * @todo    [feature] (maybe) add `{admin_email}` replaced value in `$address` (in case of comma separated input)
	 */
	function maybe_send_admin_notification( $action, $order_id, $file_name, $file_num ) {
		$actions         = get_option( 'alg_checkout_files_upload_emails_actions', array() );
		if ( ! in_array( $action, $actions ) ) {
			return;
		}
		$order           = wc_get_order( $order_id );
		$address         = get_option( 'alg_checkout_files_upload_emails_address', get_option( 'admin_email' ) );
		$subject         = get_option( 'alg_checkout_files_upload_emails_subject',
			__( '[{site_title}] Checkout files upload action in customer order ({order_number}) - {order_date}', 'checkout-files-upload-woocommerce' ) );
		$heading         = get_option( 'alg_checkout_files_upload_emails_heading',
			__( '{action}: File #{file_num}', 'checkout-files-upload-woocommerce' ) );
		$content         = get_option( 'alg_checkout_files_upload_emails_content',
			__( 'File name: {file_name}', 'checkout-files-upload-woocommerce' ) );
		$attachments     = ( 'remove_file' == $action || 'no' === get_option( 'alg_checkout_files_upload_emails_do_attach_on_upload', 'yes' ) ? '' :
			array( alg_get_alg_uploads_dir( 'checkout_files_upload' ) . '/' . get_post_meta( $order_id, '_' . 'alg_checkout_files_upload_' . $file_num, true ) ) );
		$replaced_values = array(
			'{action}'       => ( 'remove_file' == $action ?
				get_option( 'alg_checkout_files_upload_emails_action_removed',  __( 'File removed',  'checkout-files-upload-woocommerce' ) ) :
				get_option( 'alg_checkout_files_upload_emails_action_uploaded', __( 'File uploaded', 'checkout-files-upload-woocommerce' ) ) ),
			'{site_title}'   => wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES ),
			'{order_date}'   => ( $order ? date_i18n( wc_date_format(), strtotime( $order->get_date_created() ) ) : '' ),
			'{order_number}' => ( $order ? $order->get_order_number()                                             : '' ),
			'{file_name}'    => $file_name,
			'{file_num}'     => $file_num,
		);
		wc_mail(
			$address,
			str_replace( array_keys( $replaced_values ), $replaced_values, $subject ),
			$this->wrap_in_wc_email_template(
				str_replace( array_keys( $replaced_values ), $replaced_values, $content ),
				str_replace( array_keys( $replaced_values ), $replaced_values, $heading ) ),
			"Content-Type: text/html\r\n",
			$attachments
		);
	}

	/**
	 * wrap_in_wc_email_template.
	 *
	 * @version 1.4.4
	 * @since   1.4.4
	 */
	function wrap_in_wc_email_template( $content, $email_heading = '' ) {
		return $this->get_wc_email_part( 'header', $email_heading ) .
			$content .
		str_replace( '{site_title}', wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES ), $this->get_wc_email_part( 'footer' ) );
	}

	/**
	 * get_wc_email_part.
	 *
	 * @version 1.4.4
	 * @since   1.4.4
	 */
	function get_wc_email_part( $part, $email_heading = '' ) {
		ob_start();
		switch ( $part ) {
			case 'header':
				wc_get_template( 'emails/email-header.php', array( 'email_heading' => $email_heading ) );
				break;
			case 'footer':
				wc_get_template( 'emails/email-footer.php' );
				break;
		}
		return ob_get_clean();
	}

	/**
	 * alg_ajax_file_delete.
	 *
	 * @version 1.4.4
	 * @since   1.3.0
	 */
	function alg_ajax_file_delete() {
		if ( isset( $_POST['file-num'] ) ) {
			$i = $_POST['file-num'];
			if ( isset( $_POST['order_id'] ) && 0 != $_POST['order_id'] ) {
				$order_id = $_POST['order_id'];
				$order_file_name = get_post_meta( $order_id, '_' . 'alg_checkout_files_upload_' . $i, true );
				if ( '' != $order_file_name ) {
					$file_path = alg_get_alg_uploads_dir( 'checkout_files_upload' ) . '/' . $order_file_name;
					unlink( $file_path );
					$file_name = get_post_meta( $order_id, '_' . 'alg_checkout_files_upload_real_name_' . $i, true );
					delete_post_meta( $order_id, '_' . 'alg_checkout_files_upload_' . $i );
					delete_post_meta( $order_id, '_' . 'alg_checkout_files_upload_real_name_' . $i );
					$this->maybe_send_admin_notification( 'remove_file', $order_id, $file_name, $i );
				}
				echo json_encode( array(
					'result'  => 1,
					'message' => ( 'yes' === get_option( 'alg_checkout_files_upload_use_ajax_alert_success_remove', 'no' ) ?
						sprintf( get_option( 'alg_checkout_files_upload_notice_success_remove_' . $i,
							__( 'File "%s" was successfully removed.', 'checkout-files-upload-woocommerce' ) ), $file_name ) : '' ),
				) );
			} else {
				$file_name = 'alg_checkout_files_upload_' . $i ;
				unlink( $_SESSION[ $file_name ]['tmp_name'] );
				echo json_encode( array(
					'result'  => 1,
					'message' => ( 'yes' === get_option( 'alg_checkout_files_upload_use_ajax_alert_success_remove', 'no' ) ?
						sprintf( get_option( 'alg_checkout_files_upload_notice_success_remove_' . $i,
							__( 'File "%s" was successfully removed.', 'checkout-files-upload-woocommerce' ) ), $_SESSION[ $file_name ]['name'] ) : '' ),
				) );
				unset( $_SESSION[ $file_name ] );
			}
			die();
		} else {
			// Error
			echo json_encode( array(
				'result'  => 0,
				'message' => __( 'Unknown error on file remove.', 'checkout-files-upload-woocommerce' )
			) );
			die();
		}
	}

	/**
	 * get_file_download_link.
	 *
	 * @version 1.4.0
	 * @since   1.4.0
	 */
	function get_file_download_link( $file_num, $order_id, $add_timestamp = false ) {
		$args = array(
			'alg_download_checkout_file'   => $file_num,
			'_wpnonce'                     => wp_create_nonce( 'alg_download_checkout_file' ),
		);
		if ( 0 != $order_id ) {
			$args['alg_download_checkout_file_order_id'] = $order_id;
		}
		if ( $add_timestamp ) {
			$args['timestamp'] = time();
		}
		return add_query_arg( $args );
	}

	/**
	 * alg_ajax_file_upload.
	 *
	 * @version 1.4.4
	 * @since   1.3.0
	 */
	function alg_ajax_file_upload() {
		if ( isset( $_FILES['file'] ) && '' != $_FILES['file']['tmp_name'] && isset( $_POST['file-num'] ) ) {
			// Validate
			$is_valid = true;
			if ( '' != ( $file_accept = get_option( 'alg_checkout_files_upload_file_accept_' . $_POST['file-num'], '.jpg,.jpeg,.png' ) ) ) {
				// Validate file type
				$file_accept = array_map( 'trim', explode( ',', $file_accept ) );
				if ( is_array( $file_accept ) && ! empty( $file_accept ) ) {
					$real_file_name = $_FILES['file']['name'];
					$file_type      = '.' . pathinfo( $real_file_name, PATHINFO_EXTENSION );
					if ( ! in_array( strtolower( $file_type ), array_map( 'strtolower', $file_accept ) ) ) {
						$error = sprintf( get_option( 'alg_checkout_files_upload_notice_wrong_file_type_' . $_POST['file-num'],
							__( 'Wrong file type: "%s"!', 'checkout-files-upload-woocommerce' ) ), $real_file_name );
						$is_valid = false;
					}
				}
			}
			// Maybe validate image dimensions
			if ( true !== ( $error_message = $this->validate_image_dimensions( $_POST['file-num'], $_FILES['file'] ) ) ) {
				$error    = $error_message;
				$is_valid = false;
			}
			if ( $is_valid ) {
				// To session
				$file_name = 'alg_checkout_files_upload_' . $_POST['file-num'];
				if ( isset( $_SESSION[ $file_name ] ) ) {
					unlink( $_SESSION[ $file_name ]['tmp_name'] );
					unset( $_SESSION[ $file_name ] );
				}
				$_SESSION[ $file_name ] = $_FILES['file'];
				$tmp_dest_file = tempnam( sys_get_temp_dir(), 'alg' );
				move_uploaded_file( $_SESSION[ $file_name ]['tmp_name'], $tmp_dest_file );
				$_SESSION[ $file_name ]['tmp_name'] = $tmp_dest_file;
				$template = get_option( 'alg_checkout_files_upload_form_template_field_ajax', '<tr><td colspan="2">%field_html%</td></tr>' );
				$_file_name = $_SESSION[ $file_name ]['name'];
				// To order
				if ( ! empty( $_POST['order_id'] ) ) {
					$this->add_files_to_order( $_POST['order_id'], null );
					$this->maybe_send_admin_notification( 'upload_file', $_POST['order_id'], $_file_name, $_POST['file-num'] );
				}
				// Success
				echo json_encode( array(
					'result'   => 1,
					'data'     => '<a href="' . $this->get_file_download_link( $_POST['file-num'], ( isset( $_POST['order_id'] ) ? $_POST['order_id'] : 0 ) ) . '">' .
						$_file_name . '</a>',
					'data_img' => ( false !== strpos( $template, '%image%' ) ? $this->maybe_get_image( $_POST['file-num'], ( isset( $_POST['order_id'] ) ? $_POST['order_id'] : 0 ), true ) : '' ),
					'message'  => ( 'yes' === get_option( 'alg_checkout_files_upload_use_ajax_alert_success_upload', 'no' ) ?
						sprintf( get_option( 'alg_checkout_files_upload_notice_success_upload_' . $_POST['file-num'],
							__( 'File "%s" was successfully uploaded.', 'checkout-files-upload-woocommerce' ) ), $_file_name ) : '' ),
				) );
			} else {
				// Error
				echo json_encode( array(
					'result'  => 0,
					'message' => $error,
				) );
			}
			die();
		} else {
			// Error
			echo json_encode( array(
				'result'  => 0,
				'message' => __( 'Unknown error on upload.', 'checkout-files-upload-woocommerce' )
			) );
			die();
		}
	}

	/**
	 * enqueue_scripts.
	 *
	 * @version 1.4.0
	 * @since   1.3.0
	 */
	function enqueue_scripts() {
		if ( 'yes' === get_option( 'alg_checkout_files_upload_use_ajax', 'yes' ) ) {
			wp_enqueue_script( 'alg-wc-checkout-files-upload-ajax', alg_wc_checkout_files_upload()->plugin_url() . '/includes/js/alg-wc-checkout-files-upload-ajax.js',
				array( 'jquery' ), alg_wc_checkout_files_upload()->version, false );
			$max_file_size_mb = get_option( 'alg_checkout_files_upload_max_file_size_mb', 0 );
			wp_localize_script( 'alg-wc-checkout-files-upload-ajax', 'ajax_object', array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
			) );
			wp_localize_script( 'alg-wc-checkout-files-upload-ajax', 'alg_wc_checkout_files_upload', array(
				'max_file_size'                  => $max_file_size_mb * 1024 * 1024,
				'max_file_size_exceeded_message' => str_replace( '%max_file_size%', $max_file_size_mb,
					get_option( 'alg_checkout_files_upload_max_file_size_exceeded_message',
						__( 'Allowed file size exceeded (maximum %max_file_size% MB).', 'checkout-files-upload-woocommerce' ) )
				),
				'progress_bar_enabled'           => ( 'yes' === get_option( 'alg_checkout_files_upload_use_ajax_progress_bar', 'no' ) ),
			) );
			if ( 'yes' === get_option( 'alg_checkout_files_upload_use_ajax_progress_bar', 'no' ) ) {
				wp_enqueue_style( 'alg-wc-checkout-files-upload-ajax', alg_wc_checkout_files_upload()->plugin_url() . '/includes/css/alg-wc-checkout-files-upload-ajax.css',
					array(), alg_wc_checkout_files_upload()->version, 'all' );
			}
		} else {
			if ( 0 != ( $max_file_size_mb = get_option( 'alg_checkout_files_upload_max_file_size_mb', 0 ) ) ) {
				wp_enqueue_script(  'alg-wc-checkout-files-upload', alg_wc_checkout_files_upload()->plugin_url() . '/includes/js/alg-wc-checkout-files-upload.js',
					array( 'jquery' ), alg_wc_checkout_files_upload()->version, false );
				wp_localize_script( 'alg-wc-checkout-files-upload', 'alg_wc_checkout_files_upload', array(
					'max_file_size'                  => $max_file_size_mb * 1024 * 1024,
					'max_file_size_exceeded_message' => str_replace( '%max_file_size%', $max_file_size_mb,
						get_option( 'alg_checkout_files_upload_max_file_size_exceeded_message',
							__( 'Allowed file size exceeded (maximum %max_file_size% MB).', 'checkout-files-upload-woocommerce' ) )
					),
				) );
			}
		}
	}

	/**
	 * add_files_to_email_attachments.
	 *
	 * @version 1.2.0
	 * @since   1.0.0
	 */
	function add_files_to_email_attachments( $attachments, $status, $order ) {
		if (
			( 'new_order'                 === $status && 'yes' === get_option( 'alg_checkout_files_upload_attach_to_admin_new_order',           'yes' ) ) ||
			( 'customer_processing_order' === $status && 'yes' === get_option( 'alg_checkout_files_upload_attach_to_customer_processing_order', 'yes' ) )
		) {
			$order_id = ( version_compare( get_option( 'woocommerce_version', null ), '3.0.0', '<' ) ? $order->id : $order->get_id() );
			$total_files = get_post_meta( $order_id, '_' . 'alg_checkout_files_total_files', true );
			for ( $i = 1; $i <= $total_files; $i++ ) {
				$attachments[] = alg_get_alg_uploads_dir( 'checkout_files_upload' ) . '/' . get_post_meta( $order_id, '_' . 'alg_checkout_files_upload_' . $i, true );
			}
		}
		return $attachments;
	}

	/**
	 * add_files_to_order_display.
	 *
	 * @version 1.2.0
	 * @since   1.0.0
	 */
	function add_files_to_order_display( $order ) {
		$order_id = ( version_compare( get_option( 'woocommerce_version', null ), '3.0.0', '<' ) ? $order->id : $order->get_id() );
		$html = '';
		$total_files = get_post_meta( $order_id, '_' . 'alg_checkout_files_total_files', true );
		for ( $i = 1; $i <= $total_files; $i++ ) {
			$real_file_name = get_post_meta( $order_id, '_' . 'alg_checkout_files_upload_real_name_' . $i, true );
			if ( '' != $real_file_name ) {
				$html .= __( 'File', 'checkout-files-upload-woocommerce' ) . ': ' . $real_file_name . '<br>';
			}
		}
		echo $html;
	}

	/**
	 * validate_image_dimensions.
	 *
	 * @version 1.4.1
	 * @since   1.4.1
	 */
	function validate_image_dimensions( $i, $_file ) {
		if ( '' != ( $validate_image_dimensions = get_option( 'alg_checkout_files_upload_file_validate_image_dimensions_' . $i, '' ) ) ) {
			// Validate image dimensions
			$tmp_file_name = $_file['tmp_name'];
			$image_size    = getimagesize( $tmp_file_name );
			if ( is_array( $image_size ) && ! empty( $image_size[0] ) && ! empty( $image_size[1] ) ) {
				$w = get_option( 'alg_checkout_files_upload_file_validate_image_dimensions_w_' . $i, 1 );
				$h = get_option( 'alg_checkout_files_upload_file_validate_image_dimensions_h_' . $i, 1 );
				if (
					( 'validate_size'  === $validate_image_dimensions && ( $image_size[0] != $w || $image_size[1] != $h ) ) ||
					( 'validate_ratio' === $validate_image_dimensions && ( $image_size[0] / $image_size[1] != $w / $h ) )
				) {
					$file_name = $_file['name'];
					$notice    = get_option( 'alg_checkout_files_upload_notice_wrong_image_dimensions_' . $i,
						__( 'Wrong image dimensions: "%s"! Current: %current_width% x %current_height%. Required: %required_width% x %required_height%.', 'checkout-files-upload-woocommerce' ) );
					$replaced_values = array(
						'%current_width%'   => $image_size[0],
						'%current_height%'  => $image_size[1],
						'%required_width%'  => $w,
						'%required_height%' => $h,
					);
					$notice = str_replace( array_keys( $replaced_values ), $replaced_values, $notice );
					return sprintf( $notice, $file_name );
				}
			} else {
				$file_name = $_file['name'];
				return sprintf( get_option( 'alg_checkout_files_upload_notice_no_image_dimensions_' . $i,
					__( 'Couldn\'t get image dimensions: "%s"!', 'checkout-files-upload-woocommerce' ) ), $file_name );
			}
		}
		return true;
	}

	/**
	 * validate_on_checkout.
	 *
	 * @version 1.4.1
	 * @since   1.0.0
	 * @todo    [dev] move "Validate file type" to separate function
	 */
	function validate_on_checkout( $posted ) {
		$total_number = apply_filters( 'alg_wc_checkout_files_upload_option', 1, 'total_number' );
		for ( $i = 1; $i <= $total_number; $i++ ) {
			if (
				'yes' === get_option( 'alg_checkout_files_upload_enabled_' . $i, 'yes' ) &&
				$this->is_visible( $i ) &&
				'disable' != get_option( 'alg_checkout_files_upload_hook_' . $i, 'woocommerce_before_checkout_form' )
			) {
				if ( 'yes' === get_option( 'alg_checkout_files_upload_required_' . $i, 'no' ) && ! isset( $_SESSION[ 'alg_checkout_files_upload_' . $i ] ) ) {
					// Is required
					wc_add_notice( get_option( 'alg_checkout_files_upload_notice_required_' . $i, __( 'File is required!', 'checkout-files-upload-woocommerce' ) ), 'error' );
				}
				if ( ! isset( $_SESSION[ 'alg_checkout_files_upload_' . $i ] ) ) {
					continue;
				}
				if ( '' != ( $file_accept = get_option( 'alg_checkout_files_upload_file_accept_' . $i, '.jpg,.jpeg,.png' ) ) ) {
					// Validate file type
					$file_accept = explode( ',', $file_accept );
					if ( is_array( $file_accept ) && ! empty( $file_accept ) ) {
						$file_name = $_SESSION[ 'alg_checkout_files_upload_' . $i ]['name'];
						$file_type = '.' . pathinfo( $file_name, PATHINFO_EXTENSION );
						if ( ! in_array( strtolower( $file_type ), array_map( 'strtolower', $file_accept ) ) ) {
							wc_add_notice( sprintf( get_option( 'alg_checkout_files_upload_notice_wrong_file_type_' . $i,
								__( 'Wrong file type: "%s"!', 'checkout-files-upload-woocommerce' ) ), $file_name ), 'error' );
						}
					}
				}
				// Maybe validate image dimensions
				if ( true !== ( $error_message = $this->validate_image_dimensions( $i, $_SESSION[ 'alg_checkout_files_upload_' . $i ] ) ) ) {
					wc_add_notice( $error_message, 'error' );
				}
			}
		}
	}

	/**
	 * add_file_admin_order_meta_box.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	function add_file_admin_order_meta_box() {
		$screen   = 'shop_order';
		$context  = 'side';
		$priority = 'high';
		add_meta_box(
			'alg_wc_checkout_files_upload_metabox',
			__( 'Uploaded Files', 'checkout-files-upload-woocommerce' ),
			array( $this, 'create_file_admin_order_meta_box' ),
			$screen,
			$context,
			$priority
		);
	}

	/**
	 * create_file_admin_order_meta_box.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	function create_file_admin_order_meta_box() {
		$order_id = get_the_ID();
		$html = '';
		$total_files = get_post_meta( $order_id, '_' . 'alg_checkout_files_total_files', true );
		$files_exists = false;
		for ( $i = 1; $i <= $total_files; $i++ ) {
			$order_file_name = get_post_meta( $order_id, '_' . 'alg_checkout_files_upload_'           . $i, true );
			$real_file_name  = get_post_meta( $order_id, '_' . 'alg_checkout_files_upload_real_name_' . $i, true );
			if ( '' != $order_file_name ) {
				$files_exists = true;
				$html .= '<p><a href="' . add_query_arg(
					array(
						'alg_download_checkout_file_admin' => $order_file_name,
						'alg_checkout_file_number'         => $i,
					) ) . '">' . $real_file_name . '</a></p>';
			}
		}
		if ( ! $files_exists ) {
			$html .= '<p><em>' . __( 'No files uploaded.', 'checkout-files-upload-woocommerce' ) . '</em></p>';
		}
		echo $html;
	}

	/**
	 * add_files_to_order.
	 *
	 * @version 1.3.0
	 * @since   1.0.0
	 */
	function add_files_to_order( $order_id, $posted ) {
		$upload_dir = alg_get_alg_uploads_dir( 'checkout_files_upload' );
		if ( ! file_exists( $upload_dir ) ) {
			mkdir( $upload_dir, 0755, true );
		}
		$total_number = apply_filters( 'alg_wc_checkout_files_upload_option', 1, 'total_number' );
		for ( $i = 1; $i <= $total_number; $i++ ) {
			if ( isset( $_SESSION[ 'alg_checkout_files_upload_' . $i ] ) ) {
				$file_name          = $_SESSION[ 'alg_checkout_files_upload_' . $i ]['name'];
				$ext                = pathinfo( $file_name, PATHINFO_EXTENSION );
				$download_file_name = $order_id . '_' . $i . '.' . $ext;
				$file_path          = $upload_dir . '/' . $download_file_name;
				$tmp_file_name      = $_SESSION[ 'alg_checkout_files_upload_' . $i ]['tmp_name'];
				$file_data          = file_get_contents( $tmp_file_name );
				file_put_contents( $file_path, $file_data );
				unlink( $tmp_file_name );
				unset( $_SESSION[ 'alg_checkout_files_upload_' . $i ] );
				update_post_meta( $order_id, '_' . 'alg_checkout_files_upload_' . $i, $download_file_name );
				update_post_meta( $order_id, '_' . 'alg_checkout_files_upload_real_name_' . $i, $file_name );
			}
		}
		update_post_meta( $order_id, '_' . 'alg_checkout_files_total_files', $total_number );
	}

	/**
	 * process_checkout_files_upload.
	 *
	 * @version 1.4.4
	 * @since   1.0.0
	 */
	function process_checkout_files_upload() {
		if ( ! session_id() ) {
			session_start();
		}
		// Remove file
		$total_number = apply_filters( 'alg_wc_checkout_files_upload_option', 1, 'total_number' );
		for ( $i = 1; $i <= $total_number; $i++ ) {
			if ( isset( $_POST[ 'alg_remove_checkout_file_' . $i ] ) ) {
				if ( isset( $_POST[ 'alg_checkout_files_upload_order_id_' . $i ] ) ) {
					$order_id = $_POST[ 'alg_checkout_files_upload_order_id_' . $i ];
					$order_file_name = get_post_meta( $order_id, '_' . 'alg_checkout_files_upload_' . $i, true );
					if ( '' != $order_file_name ) {
						$file_path = alg_get_alg_uploads_dir( 'checkout_files_upload' ) . '/' . $order_file_name;
						unlink( $file_path );
						$file_name = get_post_meta( $order_id, '_' . 'alg_checkout_files_upload_real_name_' . $i, true );
						wc_add_notice( sprintf( get_option( 'alg_checkout_files_upload_notice_success_remove_' . $i,
							__( 'File "%s" was successfully removed.', 'checkout-files-upload-woocommerce' ) ), $file_name ) );
						delete_post_meta( $order_id, '_' . 'alg_checkout_files_upload_' . $i );
						delete_post_meta( $order_id, '_' . 'alg_checkout_files_upload_real_name_' . $i );
						$this->maybe_send_admin_notification( 'remove_file', $order_id, $file_name, $i );
					}
				} else {
					$file_name = 'alg_checkout_files_upload_' . $i;
					unlink( $_SESSION[ $file_name ]['tmp_name'] );
					wc_add_notice( sprintf( get_option( 'alg_checkout_files_upload_notice_success_remove_' . $i,
						__( 'File "%s" was successfully removed.', 'checkout-files-upload-woocommerce' ) ), $_SESSION[ $file_name ]['name'] ) );
					unset( $_SESSION[ $file_name ] );
				}
			}
		}
		// Upload file
		for ( $i = 1; $i <= $total_number; $i++ ) {
			if ( isset( $_POST[ 'alg_upload_checkout_file_' . $i ] ) ) {
				$file_name = 'alg_checkout_files_upload_' . $i;
				if ( isset( $_FILES[ $file_name ] ) && '' != $_FILES[ $file_name ]['tmp_name'] ) {
					// Validate
					$is_valid = true;
					if ( '' != ( $file_accept = get_option( 'alg_checkout_files_upload_file_accept_' . $i, '.jpg,.jpeg,.png' ) ) && isset( $_FILES[ $file_name ] ) ) {
						// Validate file type
						$file_accept = explode( ',', $file_accept );
						if ( is_array( $file_accept ) && ! empty( $file_accept ) ) {
							$real_file_name = $_FILES[ $file_name ]['name'];
							$file_type      = '.' . pathinfo( $real_file_name, PATHINFO_EXTENSION );
							if ( ! in_array( strtolower( $file_type ), array_map( 'strtolower', $file_accept ) ) ) {
								wc_add_notice( sprintf( get_option( 'alg_checkout_files_upload_notice_wrong_file_type_' . $i,
									__( 'Wrong file type: "%s"!', 'checkout-files-upload-woocommerce' ) ), $real_file_name ), 'error' );
								$is_valid = false;
							}
						}
					}
					// Maybe validate image dimensions
					if ( true !== ( $error_message = $this->validate_image_dimensions( $i, $_FILES[ $file_name ] ) ) ) {
						wc_add_notice( $error_message, 'error' );
						$is_valid = false;
					}
					if ( $is_valid ) {
						// To session
						$_SESSION[ $file_name ] = $_FILES[ $file_name ];
						$tmp_dest_file = tempnam( sys_get_temp_dir(), 'alg' );
						move_uploaded_file( $_SESSION[ $file_name ]['tmp_name'], $tmp_dest_file );
						$_SESSION[ $file_name ]['tmp_name'] = $tmp_dest_file;
						wc_add_notice( sprintf( get_option( 'alg_checkout_files_upload_notice_success_upload_' . $i,
							__( 'File "%s" was successfully uploaded.', 'checkout-files-upload-woocommerce' ) ), $_SESSION[ $file_name ]['name'] ) );
						// To order
						if ( isset( $_POST[ 'alg_checkout_files_upload_order_id_' . $i ] ) ) {
							$_file_name = $_SESSION[ $file_name ]['name'];
							$this->add_files_to_order( $_POST[ 'alg_checkout_files_upload_order_id_' . $i ], null );
							$this->maybe_send_admin_notification( 'upload_file', $_POST[ 'alg_checkout_files_upload_order_id_' . $i ], $_file_name, $i );
						}
					}
				} else {
					wc_add_notice( get_option( 'alg_checkout_files_upload_notice_upload_no_file_' . $i,
						__( 'Please select file to upload!', 'checkout-files-upload-woocommerce' ) ), 'notice' );
				}
			}
		}
		// Admin file download
		if ( isset( $_GET['alg_download_checkout_file_admin'] ) ) {
			$tmp_file_name = alg_get_alg_uploads_dir( 'checkout_files_upload' ) . '/' . $_GET['alg_download_checkout_file_admin'];
			$file_name     = get_post_meta( $_GET['post'], '_' . 'alg_checkout_files_upload_real_name_' . $_GET['alg_checkout_file_number'], true );
			if ( alg_is_user_role( 'administrator' ) || alg_is_user_role( 'shop_manager' ) ) {
				header( "Expires: 0" );
				header( "Cache-Control: must-revalidate, post-check=0, pre-check=0" );
				header( "Cache-Control: private", false );
				header( 'Content-disposition: attachment; filename=' . $file_name );
				header( "Content-Transfer-Encoding: binary" );
				header( "Content-Length: ". filesize( $tmp_file_name ) );
				readfile( $tmp_file_name );
				exit();
			}
		}
		// User file download
		if ( isset( $_GET['alg_download_checkout_file'] ) && isset( $_GET['_wpnonce'] ) && ( false !== wp_verify_nonce( $_GET['_wpnonce'], 'alg_download_checkout_file' ) ) ) {
			$i = $_GET['alg_download_checkout_file'];
			if ( ! empty( $_GET['alg_download_checkout_file_order_id'] ) ) {
				$order_id = $_GET['alg_download_checkout_file_order_id'];
				if ( ! ( $order = wc_get_order( $order_id ) ) ) {
					return;
				}
				if ( isset( $_GET['key'] ) ) {
					// Thank you page
					if ( ! $order->key_is_valid( $_GET['key'] ) ) {
						return;
					}
				} else {
					// My Account
					if ( ! function_exists( 'is_user_logged_in' ) || ! function_exists( 'get_current_user_id' ) ) {
						require_once( ABSPATH . 'wp-includes/pluggable.php' );
					}
					if ( ! is_user_logged_in() || $order->get_customer_id() != get_current_user_id() ) {
						return;
					}
				}
				$order_file_name = get_post_meta( $order_id, '_' . 'alg_checkout_files_upload_' . $i, true );
				$tmp_file_name   = alg_get_alg_uploads_dir( 'checkout_files_upload' ) . '/' . $order_file_name;
				$file_name       = get_post_meta( $order_id, '_' . 'alg_checkout_files_upload_real_name_' . $i, true );
			} else {
				$tmp_file_name   = $_SESSION[ 'alg_checkout_files_upload_' . $i ]['tmp_name'];
				$file_name       = $_SESSION[ 'alg_checkout_files_upload_' . $i ]['name'];
			}
			header( "Expires: 0" );
			header( "Cache-Control: must-revalidate, post-check=0, pre-check=0" );
			header( "Cache-Control: private", false );
			header( 'Content-disposition: attachment; filename=' . $file_name );
			header( "Content-Transfer-Encoding: binary" );
			header( "Content-Length: ". filesize( $tmp_file_name ) );
			readfile( $tmp_file_name );
			exit();
		}
	}

	/**
	 * is_visible.
	 *
	 * @version 1.3.0
	 * @since   1.0.0
	 */
	function is_visible( $i, $order_id = 0 ) {
		// Include by product id
		$products_in = get_option( 'alg_checkout_files_upload_show_products_in_' . $i, '' );
		if ( ! empty( $products_in ) ) {
			$do_skip_by_products = true;
			if ( 0 != $order_id ) {
				$the_order = wc_get_order( $order_id );
				$the_items = $the_order->get_items();
			} else {
				$the_items = WC()->cart->get_cart();
			}
			foreach ( $the_items as $cart_item_key => $values ) {
				if ( in_array( $values['product_id'], $products_in ) ) {
					$do_skip_by_products = false;
					break;
				}
			}
			if ( $do_skip_by_products ) return false;
		}
		// Include by product category
		$categories_in = get_option( 'alg_checkout_files_upload_show_cats_in_' . $i, '' );
		if ( ! empty( $categories_in ) ) {
			$do_skip_by_cats = true;
			if ( 0 != $order_id ) {
				$the_order = wc_get_order( $order_id );
				$the_items = $the_order->get_items();
			} else {
				$the_items = WC()->cart->get_cart();
			}
			foreach ( $the_items as $cart_item_key => $values ) {
				$product_categories = get_the_terms( $values['product_id'], 'product_cat' );
				if ( empty( $product_categories ) ) continue;
				foreach( $product_categories as $product_category ) {
					if ( in_array( $product_category->term_id, $categories_in ) ) {
						$do_skip_by_cats = false;
						break;
					}
				}
				if ( ! $do_skip_by_cats ) break;
			}
			if ( $do_skip_by_cats ) return false;
		}
		// Include by product tag
		$tags_in = get_option( 'alg_checkout_files_upload_show_tags_in_' . $i, '' );
		if ( ! empty( $tags_in ) ) {
			$do_skip_by_tags = true;
			if ( 0 != $order_id ) {
				$the_order = wc_get_order( $order_id );
				$the_items = $the_order->get_items();
			} else {
				$the_items = WC()->cart->get_cart();
			}
			foreach ( $the_items as $cart_item_key => $values ) {
				$product_tags = get_the_terms( $values['product_id'], 'product_tag' );
				if ( empty( $product_tags ) ) continue;
				foreach( $product_tags as $product_tag ) {
					if ( in_array( $product_tag->term_id, $tags_in ) ) {
						$do_skip_by_tags = false;
						break;
					}
				}
				if ( ! $do_skip_by_tags ) break;
			}
			if ( $do_skip_by_tags ) return false;
		}
		return true;
	}

	/**
	 * get_the_form.
	 *
	 * @version 1.4.0
	 * @since   1.3.0
	 */
	function get_the_form( $i, $file_name, $order_id = 0 ) {
		return ( 'yes' === get_option( 'alg_checkout_files_upload_use_ajax', 'yes' ) ?
			$this->get_the_form_ajax( $i, $file_name, $order_id ) :
			$this->get_the_form_simple( $i, $file_name, $order_id )
		);
	}

	/**
	 * get_the_form_ajax.
	 *
	 * @version 1.4.5
	 * @since   1.3.0
	 * @todo    [dev] add `do_shortcode()` to all notices
	 */
	function get_the_form_part_label( $i ) {
		if ( '' != ( $the_label = do_shortcode( get_option( 'alg_checkout_files_upload_label_' . $i, __( 'Please select file to upload', 'checkout-files-upload-woocommerce' ) ) ) ) ) {
			$template = get_option( 'alg_checkout_files_upload_form_template_label',
				'<tr><td colspan="2"><label for="%field_id%">%field_label%</label>%required_html%</td></tr>' );
			$required_html = ( 'yes' === get_option( 'alg_checkout_files_upload_required_' . $i, 'no' ) ) ?
				'&nbsp;<abbr class="required" title="required">*</abbr>' : '';
			return str_replace(
				array( '%field_id%', '%field_label%', '%required_html%' ),
				array( 'alg_checkout_files_upload_' . $i, $the_label, $required_html ),
				$template
			);
		}
		return '';
	}

	/**
	 * maybe_get_image.
	 *
	 * @version 1.4.1
	 * @since   1.4.0
	 * @todo    [dev] use another way to check if it's an image (i.e. not `getimagesize()`)
	 */
	function maybe_get_image( $i, $order_id = 0, $add_timestamp = false ) {
		$tmp_file_name = false;
		if ( 0 != $order_id ) {
			$passed = false;
			if ( $order = wc_get_order( $order_id ) ) {
				if ( ! empty( $_REQUEST['key'] ) ) {
					// Thank you page
					if ( $order->key_is_valid( $_REQUEST['key'] ) ) {
						$passed = true;
					}
				} else {
					// My Account
					if ( ! function_exists( 'is_user_logged_in' ) || ! function_exists( 'get_current_user_id' ) ) {
						require_once( ABSPATH . 'wp-includes/pluggable.php' );
					}
					if ( is_user_logged_in() && $order->get_customer_id() == get_current_user_id() ) {
						$passed = true;
					}
				}
			}
			if ( $passed ) {
				$tmp_file_name = alg_get_alg_uploads_dir( 'checkout_files_upload' ) . '/' . get_post_meta( $order_id, '_' . 'alg_checkout_files_upload_' . $i, true );
			}
		} elseif ( isset( $_SESSION[ 'alg_checkout_files_upload_' . $i ]['tmp_name'] ) ) {
			$tmp_file_name = $_SESSION[ 'alg_checkout_files_upload_' . $i ]['tmp_name'];
		}
		return ( $tmp_file_name && @is_array( getimagesize( $tmp_file_name ) ) ?
			'<img style="' . get_option( 'alg_checkout_files_upload_form_image_style', 'width:64px;' ) . '" src="' . $this->get_file_download_link( $i, $order_id, $add_timestamp ) . '">' :
			'' );
	}

	/**
	 * get_the_form_ajax.
	 *
	 * @version 1.4.1
	 * @since   1.3.0
	 * @todo    [feature] more options for "delete" button styling (i.e. `&times;`)
	 */
	function get_the_form_ajax( $i, $file_name, $order_id = 0 ) {
		$html = '';
		$html .= '<form enctype="multipart/form-data" action="" method="POST" id="alg_checkout_files_upload_form_' . $i . '">';
		$html .= get_option( 'alg_checkout_files_upload_form_template_before', '<table>' );
		$html .= $this->get_the_form_part_label( $i );
		$field_html = '';
		$field_html .= '<div style="margin-bottom:5px;">';
		$field_html .= '<input type="file" class="alg_checkout_files_upload_file_input" file-num="' . $i . '" name="alg_checkout_files_upload_' . $i . '" ' .
			'id="alg_checkout_files_upload_' . $i . '" ' .
			'accept="' . get_option( 'alg_checkout_files_upload_file_accept_' . $i, '.jpg,.jpeg,.png' ) . '" ' .
			'style="' . ( '' == $file_name ? '' : 'display:none;' ). '">';
		$field_html .= '</div>';
		$field_html .= '<div id="alg_checkout_files_upload_result_' . $i . '" style="' . ( '' == $file_name ? 'display:none;margin-bottom:5px;' : 'margin-bottom:5px;' ). '">';
		$field_html .= '<span id="alg_checkout_files_upload_result_file_name_' . $i . '">' .
			( '' == $file_name ? '' : '<a href="' . $this->get_file_download_link( $i, $order_id ) . '">' . $file_name . '</a>' ) . '</span>';
		$field_html .= ' ';
		$field_html .= '<a href="" class="alg_checkout_files_upload_result_delete" file-num="' . $i . '" style="' .
			get_option( 'alg_checkout_files_upload_form_style_ajax_delete', 'color:red;' ) . '" title="' . __( 'Remove', 'checkout-files-upload-woocommerce' ) . '">&times;</a>';
		$field_html .= '</div>';
		$template = get_option( 'alg_checkout_files_upload_form_template_field_ajax', '<tr><td colspan="2">%field_html%</td></tr>' );
		$replaced_values = array(
			'%field_html%'    => $field_html,
			'%image%'         => ( false !== strpos( $template, '%image%' ) ? '<span id="alg_checkout_files_upload_image_' . $i . '">' . $this->maybe_get_image( $i, $order_id ) . '</span>' : '' ),
		);
		$html .= str_replace( array_keys( $replaced_values ), $replaced_values, $template );
		$html .= get_option( 'alg_checkout_files_upload_form_template_after', '</table>' );
		$html .= '<input type="hidden" id="alg_checkout_files_upload_order_id_' . $i  . '"  name="alg_checkout_files_upload_order_id_' . $i  . '" value="' . $order_id . '">';
		$html .= '<input type="hidden" id="alg_checkout_files_upload_order_key_' . $i . '"  name="alg_checkout_files_upload_order_key_' . $i . '" value="' .
			( isset( $_REQUEST['key'] ) ? esc_html( $_REQUEST['key'] ) : 0 ) . '">';
		$html .= '</form>';
		if ( 'yes' === get_option( 'alg_checkout_files_upload_use_ajax_progress_bar', 'no' ) ) {
			$html .= '<div id="alg-wc-checkout-files-upload-progress-wrapper-' . $i . '">' .
				'<div class="alg-wc-checkout-files-upload-progress-bar" style="' . ( '' == $file_name ? '' : 'width:100%;' ). '"></div>' .
				'<div class="alg-wc-checkout-files-upload-progress-status">' . ( '' == $file_name ? '0%' : '100%' ). '</div>' .
			'</div>';
		}
		return $html;
	}

	/**
	 * get_the_form_simple.
	 *
	 * @version 1.4.5
	 * @since   1.0.0
	 */
	function get_the_form_simple( $i, $file_name, $order_id = 0 ) {
		$html = '';
		$html .= '<form enctype="multipart/form-data" action="" method="POST">';
		$html .= get_option( 'alg_checkout_files_upload_form_template_before', '<table>' );
		$html .= $this->get_the_form_part_label( $i );
		if ( '' == $file_name ) {
			$field_html = '<input type="file" class="alg_checkout_files_upload_file_input" name="alg_checkout_files_upload_' . $i . '" id="alg_checkout_files_upload_' . $i .
				'" accept="' . get_option( 'alg_checkout_files_upload_file_accept_' . $i, '.jpg,.jpeg,.png' ) . '">';
			$button_html = '<input type="submit"' .
				' class="button alt"' .
				' style="width:100%;"' .
				' name="alg_upload_checkout_file_' . $i . '"' .
				' id="alg_upload_checkout_file_' . $i . '"' .
				' value="'      . do_shortcode( get_option( 'alg_checkout_files_upload_label_upload_button_' . $i, __( 'Upload', 'checkout-files-upload-woocommerce' ) ) ) . '"' .
				' data-value="' . do_shortcode( get_option( 'alg_checkout_files_upload_label_upload_button_' . $i, __( 'Upload', 'checkout-files-upload-woocommerce' ) ) ) . '">';
		} else {
			$field_html  = '<a href="' . $this->get_file_download_link( $i, $order_id ) . '">' . $file_name . '</a>';
			$button_html = '<input type="submit"' .
				' class="button"' .
				' style="width:100%;"' .
				' name="alg_remove_checkout_file_' . $i . '"' .
				' id="alg_remove_checkout_file_' . $i . '"' .
				' value="'      . do_shortcode( get_option( 'alg_checkout_files_upload_label_remove_button_' . $i, __( 'Remove', 'checkout-files-upload-woocommerce' ) ) ) . '"' .
				' data-value="' . do_shortcode( get_option( 'alg_checkout_files_upload_label_remove_button_' . $i, __( 'Remove', 'checkout-files-upload-woocommerce' ) ) ) . '">';
		}
		$template = get_option( 'alg_checkout_files_upload_form_template_field',
			'<tr><td style="width:50%;">%field_html%</td><td style="width:50%;">%button_html%</td></tr>' );
		$replaced_values = array(
			'%field_html%'    => $field_html,
			'%button_html%'   => $button_html,
			'%image%'         => ( false !== strpos( $template, '%image%' ) ? $this->maybe_get_image( $i, $order_id ) : '' ),
		);
		$html .= str_replace( array_keys( $replaced_values ), $replaced_values, $template );
		$html .= get_option( 'alg_checkout_files_upload_form_template_after', '</table>' );
		if ( 0 != $order_id ) {
			$html .= '<input type="hidden" name="alg_checkout_files_upload_order_id_' . $i . '" value="' . $order_id . '">';
		}
		$html .= '</form>';
		return $html;
	}

	/**
	 * add_files_upload_form_to_thankyou_and_myaccount_page.
	 *
	 * @version 1.3.0
	 * @since   1.0.0
	 */
	function add_files_upload_form_to_thankyou_and_myaccount_page( $order_id ) {
		$html = '';
		$total_number = apply_filters( 'alg_wc_checkout_files_upload_option', 1, 'total_number' );
		$current_filter = current_filter();
		for ( $i = 1; $i <= $total_number; $i++ ) {
			if ( 'yes' === get_option( 'alg_checkout_files_upload_enabled_' . $i, 'yes' ) && $this->is_visible( $i, $order_id ) ) {
				if (
					( 'yes' === get_option( 'alg_checkout_files_upload_add_to_thankyou_'  . $i, 'no' ) && 'woocommerce_thankyou'   === $current_filter ) ||
					( 'yes' === get_option( 'alg_checkout_files_upload_add_to_myaccount_' . $i, 'no' ) && 'woocommerce_view_order' === $current_filter )
				) {
					$file_name = get_post_meta( $order_id, '_' . 'alg_checkout_files_upload_real_name_' . $i, true );
					$html .= $this->get_the_form( $i, $file_name, $order_id );
				}
			}
		}
		echo $html;
	}

	/**
	 * add_files_upload_form_to_checkout_frontend.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	function add_files_upload_form_to_checkout_frontend() {
		$this->add_files_upload_form_to_checkout_frontend_all();
	}

	/**
	 * add_files_upload_form_to_checkout_frontend_all.
	 *
	 * @version 1.3.0
	 * @since   1.0.0
	 */
	function add_files_upload_form_to_checkout_frontend_all( $is_direct_call = false ) {
		$html = '';
		$total_number = apply_filters( 'alg_wc_checkout_files_upload_option', 1, 'total_number' );
		if ( ! $is_direct_call ) {
			$current_filter = current_filter();
			$current_filter_priority = alg_current_filter_priority();
		}
		for ( $i = 1; $i <= $total_number; $i++ ) {
			$is_filter_ok = ( $is_direct_call ) ? true : (
				$current_filter === get_option( 'alg_checkout_files_upload_hook_' . $i, 'woocommerce_before_checkout_form' ) &&
				$current_filter_priority == get_option( 'alg_checkout_files_upload_hook_priority_' . $i, 20 )
			);
			if ( 'yes' === get_option( 'alg_checkout_files_upload_enabled_' . $i, 'yes' ) && $is_filter_ok && $this->is_visible( $i ) ) {
				$file_name = ( isset( $_SESSION[ 'alg_checkout_files_upload_' . $i ] ) ) ? $_SESSION[ 'alg_checkout_files_upload_' . $i ]['name'] : '';
				$html .= $this->get_the_form( $i, $file_name );
			}
		}
		echo $html;
	}

}

endif;

return new Alg_WC_Checkout_Files_Upload_Main();
