/**
 * Checkout Files Upload for WooCommerce - admin scripts
 *
 * @version 2.1.0
 * @since   2.1.0
 * @author  WP Wham
 */

(function( $ ){
	
	$( document ).ready( function(){
		
		$( '.wpwham-checkout-files-upload-file-delete-button' ).on( 'click', function(){
			return confirm( wpwham_checkout_files_upload_admin.i18n.confirmation_message );
		});
		
	});
	
})( jQuery );
