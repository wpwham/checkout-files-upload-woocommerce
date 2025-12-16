<?php
/**
 * Checkout Files Upload for WooCommerce - Review Suggestion
 *
 * @version 2.2.3
 * @since   2.2.3
 * @author  WP Wham
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'Alg_WC_Checkout_Files_Upload_Review' ) ) :

class Alg_WC_Checkout_Files_Upload_Review {

	/**
	 * Constructor.
	 *
	 * @version 2.2.3
	 * @since   2.2.3
	 */
	function __construct() {
		add_action( 'admin_init', array( $this, 'review_suggestion' ) );
	}

	/**
	 * Review suggestion notice in admin.
	 *
	 * @version 2.2.3
	 * @since   2.2.3
	 */
	function review_suggestion() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

    /* Show this on Checkout Files Upload settings page only?
		if ( ! ( isset( $_GET['page'] ) && $_GET['page'] === 'wc-settings' && isset( $_GET['tab'] ) && $_GET['tab'] === 'alg_wc_checkout_files_upload' ) ) {
			return;
		}
    */

		$dismissed = get_option( 'alg_checkout_files_upload_review_dismissed' );
		if ( $dismissed === 'permanently' || ( is_numeric( $dismissed ) && $dismissed > time() ) ) {
			return;
		}

		if ( isset( $_GET['alg_checkout_files_upload_dismiss_review'] ) && isset( $_GET['_wpnonce'] ) ) {
			if ( wp_verify_nonce( $_GET['_wpnonce'], 'alg_checkout_files_upload_dismiss' ) ) {
				if ( isset( $_GET['later'] ) ) {
					update_option( 'alg_checkout_files_upload_review_dismissed', time() + ( DAY_IN_SECONDS * 30 ) );
				} else {
					update_option( 'alg_checkout_files_upload_review_dismissed', 'permanently' );
				}
				wp_redirect( remove_query_arg( array( 'alg_checkout_files_upload_dismiss_review', '_wpnonce', 'later' ) ) );
				exit;
			}
		}

		$installed = get_option( 'alg_checkout_files_upload_installed_time' );
		if ( ! $installed ) {
			update_option( 'alg_checkout_files_upload_installed_time', time() );
			return;
		}

		if ( ( $installed + ( DAY_IN_SECONDS * 7 ) ) > time() ) {
			return;
		}

		if ( ! $this->has_minimum_usage() ) {
			return;
		}

		$review_url = 'https://wordpress.org/support/plugin/checkout-files-upload-woocommerce/reviews/?rate=5#new-post';
		$dismiss_url = wp_nonce_url( add_query_arg( 'alg_checkout_files_upload_dismiss_review', '1' ), 'alg_checkout_files_upload_dismiss' );
		$later_url = add_query_arg( 'later', '1', $dismiss_url );
		?>
		<div class="updated woocommerce-message">
			<a class="woocommerce-message-close notice-dismiss" href="<?php echo esc_url( $later_url ); ?>"><?php esc_html_e( 'Dismiss', 'checkout-files-upload-woocommerce' ); ?></a>
			<p><?php esc_html_e( 'Finding Checkout Files Upload useful? We\'d appreciate a 5-star review!', 'checkout-files-upload-woocommerce' ); ?></p>
			<p><a href="<?php echo esc_url( $review_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Sure, you deserve it', 'checkout-files-upload-woocommerce' ); ?></a></p>
			<p><a href="<?php echo esc_url( $later_url ); ?>"><?php esc_html_e( 'Maybe later', 'checkout-files-upload-woocommerce' ); ?></a></p>
			<p><a href="<?php echo esc_url( $dismiss_url ); ?>"><?php esc_html_e( 'I already did!', 'checkout-files-upload-woocommerce' ); ?></a></p>
		</div>
		<?php
	}

	/**
	 * Check if file upload field is configured.
	 *
	 * @version 2.2.3
	 * @since   2.2.3
	 */
	function has_minimum_usage() {
		return 'disable' != get_option( 'alg_checkout_files_upload_hook_1', 'woocommerce_before_checkout_form' );
	}
}

endif;

return new Alg_WC_Checkout_Files_Upload_Review();

