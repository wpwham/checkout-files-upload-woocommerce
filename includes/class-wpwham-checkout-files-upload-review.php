<?php
/**
 * Checkout Files Upload for WooCommerce - Review Suggestion
 *
 * @version x.x.x
 * @since   x.x.x
 * @author  WP Wham
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'WPWham_Checkout_Files_Upload_Review' ) ) :

class WPWham_Checkout_Files_Upload_Review {

	/**
	 * Constructor.
	 *
	 * @version x.x.x
	 * @since   x.x.x
	 */
	function __construct() {
		add_action( 'admin_init', array( $this, 'review_suggestion' ) );
	}

	/**
	 * Review suggestion notice in admin.
	 *
	 * @version x.x.x
	 * @since   x.x.x
	 */
	function review_suggestion() {
		if ( wp_doing_ajax() ) {
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

        /* Show this on Checkout Files Upload settings page only?
		if ( ! ( isset( $_GET['page'] ) && $_GET['page'] === 'wc-settings' && isset( $_GET['tab'] ) && $_GET['tab'] === 'alg_wc_checkout_files_upload' ) ) {
			return;
		}
        */

		$dismissed = get_option( 'wpwham_checkout_files_upload_review_dismissed' );
		if ( $dismissed === 'permanently' || ( is_numeric( $dismissed ) && $dismissed > time() ) ) {
			return;
		}

		if ( isset( $_GET['wpwham_checkout_files_upload_dismiss_review'] ) && isset( $_GET['_wpnonce'] ) ) {
			if ( wp_verify_nonce( $_GET['_wpnonce'], 'wpwham_checkout_files_upload_dismiss' ) ) {
				if ( isset( $_GET['later'] ) ) {
					update_option( 'wpwham_checkout_files_upload_review_dismissed', time() + ( DAY_IN_SECONDS * 30 ) );
				} else {
					update_option( 'wpwham_checkout_files_upload_review_dismissed', 'permanently' );
				}
				wp_redirect( remove_query_arg( array( 'wpwham_checkout_files_upload_dismiss_review', '_wpnonce', 'later' ) ) );
				exit;
			}
		}

		$installed = get_option( 'wpwham_checkout_files_upload_installed_time' );
		if ( ! $installed ) {
			update_option( 'wpwham_checkout_files_upload_installed_time', time() );
			return;
		}

		if ( ( $installed + ( DAY_IN_SECONDS * 7 ) ) > time() ) {
			return;
		}

		if ( ! $this->has_minimum_usage() ) {
			return;
		}

		$review_url = 'https://wordpress.org/support/plugin/checkout-files-upload-woocommerce/reviews/?rate=5#new-post';
		$dismiss_url = wp_nonce_url( add_query_arg( 'wpwham_checkout_files_upload_dismiss_review', '1' ), 'wpwham_checkout_files_upload_dismiss' );
		$later_url = add_query_arg( 'later', '1', $dismiss_url );
		?>
		<div class="notice notice-info woocommerce-message">
			<a class="woocommerce-message-close notice-dismiss" href="<?php echo esc_url( $later_url ); ?>"><?php esc_html_e( 'Dismiss', 'checkout-files-upload-woocommerce' ); ?></a>
			<p><?php esc_html_e( 'Finding Checkout Files Upload useful? We\'d appreciate a 5-star review!', 'checkout-files-upload-woocommerce' ); ?></p>
			<p><a href="<?php echo esc_url( $review_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Sure, you deserve it', 'checkout-files-upload-woocommerce' ); ?></a></p>
			<p><a href="<?php echo esc_url( $later_url ); ?>"><?php esc_html_e( 'Maybe later', 'checkout-files-upload-woocommerce' ); ?></a></p>
			<p><a href="<?php echo esc_url( $dismiss_url ); ?>"><?php esc_html_e( 'I already did!', 'checkout-files-upload-woocommerce' ); ?></a></p>
		</div>
		<?php
	}

	/**
	 * Check if an order has an uploaded checkout file.
	 *
	 * @version x.x.x
	 * @since   x.x.x
	 */
	function has_minimum_usage() {
		$cached = get_transient( 'wpwham_checkout_files_upload_has_usage' );
		if ( $cached !== false ) {
			return $cached === 'yes';
		}

		global $wpdb;
		if (
			method_exists( '\Automattic\WooCommerce\Utilities\OrderUtil', 'custom_orders_table_usage_is_enabled' )
			&& \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()
		) {
			$result = $wpdb->get_var(
				"SELECT order_id FROM {$wpdb->prefix}wc_orders_meta
				WHERE meta_key = '_alg_checkout_files_upload_1'
				AND meta_value != ''
				LIMIT 1"
			);
		} else {
			$result = $wpdb->get_var(
				"SELECT post_id FROM {$wpdb->postmeta}
				WHERE meta_key = '_alg_checkout_files_upload_1'
				AND meta_value != ''
				LIMIT 1"
			);
		}
		$has_usage = ! empty( $result );

		set_transient( 'wpwham_checkout_files_upload_has_usage', $has_usage ? 'yes' : 'no', DAY_IN_SECONDS );
		return $has_usage;
	}
}

endif;

return new WPWham_Checkout_Files_Upload_Review();
