=== Form Plant ===
Contributors: reiji-sato
Tags: form, contact form, custom form, inquiry
Requires at least: 6.0
Tested up to: 6.9
Stable tag: 1.0.0
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A versatile form plugin with easy modal-based setup and flexible customization options.

== Description ==

Form Plant is a versatile form plugin with easy modal-based setup and flexible customization options.

= Features =

* Intuitive modal UI for field configuration
* Custom HTML template support
* Confirmation screen before submission
* Custom validation messages
* Post-submission actions (message / custom HTML / redirect)
* Two types of date input (calendar / dropdown)
* reCAPTCHA v3 support
* External site embedding via iframe / JavaScript
* Submission data storage and management
* CSV export of submission data
* File upload support

= Supported Field Types =

* Text
* Textarea
* Email
* Phone
* Number
* URL
* Date (Calendar)
* Date (Dropdown)
* Time
* Select
* Checkbox
* Radio
* File Upload
* Hidden
* HTML

= External Services =

This plugin optionally integrates with Google reCAPTCHA v3 for spam protection:

* Service: Google reCAPTCHA
* API Documentation: https://developers.google.com/recaptcha/docs/v3
* Terms of Use: https://policies.google.com/terms
* Privacy Policy: https://policies.google.com/privacy

When reCAPTCHA is enabled in form settings, this plugin will:
- Load the reCAPTCHA JavaScript library from Google's servers
- Send form submission data to Google for spam analysis
- Include user's IP address and browser information in the request

reCAPTCHA is disabled by default and requires explicit activation by the site administrator.

== Installation ==

1. Upload the plugin folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Create a form from the 'Form Plant' menu

== Frequently Asked Questions ==

= How do I display a form? =

Use the shortcode `[fplant id="YOUR_FORM_ID"]` in your post or page content.

= Can I customize the form appearance? =

Yes, you can add custom CSS through the form settings, or use custom HTML templates for complete control over the layout.

= Does it support file uploads? =

Yes, the File Upload field type allows users to upload files with configurable size limits and file type restrictions.

== Changelog ==

= 1.0.0 =
* Initial release
