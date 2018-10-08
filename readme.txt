=== Checkout Files Upload for WooCommerce ===
Contributors: wpwham
Tags: woocommerce, checkout files upload, checkout, woo commerce
Requires at least: 4.4
Tested up to: 4.9
Stable tag: 1.5.0
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Let your customers upload files on (or after) WooCommerce checkout.

== Description ==

Checkout Files Upload for WooCommerce plugin lets your customers upload files on (or after) WooCommerce checkout.

= Features =

Set field's **position** on WooCommerce checkout page:

* Before checkout form.
* After checkout form.
* Do not add on checkout.

Set if file upload **is required**.

If you need files to be uploaded after order is created, you can optionally add field to:

* WooCommerce **Thank You** (i.e. **Order Received**) page.
* WooCommerce **My Account** page.

Add custom **label** to the field.

Set **accepted file types**.

Set custom Upload and Remove **button labels**.

Set **custom messages**:

* "Wrong file type".
* "Wrong image dimensions" and "Couldn't get image dimensions".
* "File is required".
* "File was successfully uploaded".
* "No file selected".
* "File was successfully removed".

Optionally set field to show up only if in cart there are selected:

* **Products**.
* **Product categories**.
* **Product tags**.

Add uploaded files to admin and customers **emails**.

Send **additional emails** if user uploads or removes files on "Thank You" or "My Account" pages.

**Customize** the frontend files upload form.

Optionally enable **AJAX form** for file uploads.

Set **max file size** option.

Optionally **validate image dimensions**.

= Feedback =
* We are open to your suggestions and feedback. Thank you for using or trying out one of our plugins!
* Drop us a line at [www.wpwham.com](https://wpwham.com).

= More =
* Visit the [Checkout Files Upload for WooCommerce plugin page](https://wpwham.com/products/checkout-files-upload-for-woocommerce/).

== Installation ==

1. Upload the entire plugin folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the "Plugins" menu in WordPress.
3. Start by visiting plugin settings at "WooCommerce > Settings > Checkout Files Upload".

== Changelog ==

= 1.5.0 - 2018-10-08 =
* FIX: php notice
* UPDATE: updated .pot file for translations

= 1.4.5 - 2018-10-01 =
* Dev - `[alg_wc_cfu_translate]` shortcode added and `do_shortcode()` is now applied to each file's "Labels".

= 1.4.4 - 2018-09-13 =
* Dev - Emails - "Additional Emails Options" subsection added.
* Dev - "Raw" input is now allowed in all textarea admin settings fields.
* Dev - Code refactoring - "Reset" function re-written.
* Dev - Code refactoring - `custom_number_checkout_files_upload` settings type removed.
* Dev - "Your settings have been saved" admin notice added.

= 1.4.3 - 2018-09-10 =
* Dev - "Author URI" updated.

= 1.4.2 - 2018-09-10 =
* Dev - "Contributors" updated.

= 1.4.1 - 2018-08-27 =
* Fix - `%image%` in AJAX form fixed on "Thank you" and "My Account" pages.
* Dev - "Validate image dimensions" options added for each file.
* Dev - Minor code refactoring.
* Dev - Minor admin settings restyling.

= 1.4.0 - 2018-08-25 =
* Fix - User file download fixed on "Thank you" and "My Account" pages.
* Dev - `%image%` replaced value added.
* Dev - "AJAX form" now is enabled (`yes`) in settings by default.
* Dev - Code refactoring.
* Dev - Minor admin settings restyling.
* Dev - Plugin URI updated.

= 1.3.0 - 2018-06-09 =
* Fix - Case insensitive comparison of the "Accepted file types" options.
* Fix - Default values fixed for all `get_option()` calls.
* Dev - "AJAX form" options added.
* Dev - "Max file size" options added.
* Dev - Filter rewritten.
* Dev - Admin settings - "Reset settings" section added.
* Dev - Admin settings - Files settings added as separate sections.
* Dev - Admin settings - Minor changes: restyling; `select` option type changed to `wc-enhanced-select`; settings array saved as main class property.

= 1.2.0 - 2017-05-10 =
* Fix - `Call to undefined function is_shop_manager()` error fixed.
* Dev - WooCommerce v3.x.x compatibility - Order ID - using function instead of accessing property directly.
* Dev - `load_plugin_textdomain` moved to constructor from `init` hook.
* Dev - Plugin link changed from `http://coder.fm` to `https://wpcodefactory.com`.

= 1.1.1 - 2016-12-07 =
* Dev - `alg_current_filter_priority()` modified for compatibility with WordPress since v4.7.
* Dev - Language (POT) file updated.
* Dev - Checking for Pro modified.

= 1.1.0 - 2016-11-28 =
* Dev - "Form Template Options" settings section added.
* Dev - Language (POT) file added.
* Dev - "Emails Options" settings moved to separate section.

= 1.0.0 - 2016-09-05 =
* Initial Release.

== Upgrade Notice ==

= 1.0.0 =
This is the first release of the plugin.
