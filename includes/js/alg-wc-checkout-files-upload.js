/**
 * alg-wc-checkout-files-upload.js
 *
 * @version 1.3.0
 * @since   1.3.0
 * @author  Algoritmika Ltd.
 */

jQuery(document).ready(function() {
	jQuery(".alg_checkout_files_upload_file_input").on("change", function (e) {
		var f = this.files[0]; // because this is single file upload we use only first index
		if (f.size > alg_wc_checkout_files_upload.max_file_size || f.fileSize > alg_wc_checkout_files_upload.max_file_size) {
			alert(alg_wc_checkout_files_upload.max_file_size_exceeded_message);
			this.value = null; // reset file upload control
		}
	});
});
