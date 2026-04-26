=== Form Plant ===
Contributors: reiji-sato
Tags: form, contact form, custom form, email, block
Requires at least: 6.0
Tested up to: 6.9
Stable tag: 1.1.0
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A form plugin with built-in confirmation screen, submission data storage, and external site embedding — ready to use right out of the box.

== Description ==

Form Plant is a WordPress form plugin that includes a confirmation screen, submission data storage, and CSV export as standard features — no add-ons required.

Many form plugins require extra extensions for confirmation pages or data management. Form Plant gives you these essentials from the start, so you can set up a fully functional contact form in minutes.

**Why Form Plant?**

* **Confirmation screen included** — Let users review their input before submitting. No extra plugin needed.
* **Submission data storage** — All form entries are saved in the database and manageable from the admin panel.
* **CSV export** — Download submission data anytime for reporting or backup.
* **Embed on external sites** — Display your forms on any website via iframe or JavaScript snippet, not just within WordPress.
* **Quick setup** — An intuitive modal UI lets you build forms without touching code.
* **Flexible customization** — Custom HTML templates, validation messages, and post-submission actions give you full control when you need it.

= Features =

* Intuitive modal UI for field configuration
* Block editor (Gutenberg) integration — pick a form from a dropdown using the dedicated "Form Plant" block
* Email notifications (admin notification and auto-reply)
* Custom HTML template support
* Confirmation screen before submission
* Custom validation messages
* Post-submission actions (message / custom HTML / redirect)
* Two types of date input (calendar / dropdown)
* Postal code lookup that auto-fills address fields (Japan)
* Multiple spam protection options: honeypot, time-based check, IP rate limit, disposable email blocking, Google reCAPTCHA v2/v3, Cloudflare Turnstile
* External site embedding via iframe / JavaScript
* Multiple custom CSS file uploads and design presets
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
* Name (parts)
* Name (Kana)
* Postal Code (with address auto-fill)
* Address (Japan / international)
* Prefecture
* Date (Calendar)
* Date (Dropdown)
* Time
* Password (with optional strength meter)
* Select
* Checkbox
* Radio
* File Upload
* Hidden
* HTML

= External Services =

This plugin optionally integrates with Google reCAPTCHA (v2 Checkbox and v3 Score-based) for spam protection:

* Service: Google reCAPTCHA
* API Documentation (v2): https://developers.google.com/recaptcha/docs/display
* API Documentation (v3): https://developers.google.com/recaptcha/docs/v3
* Terms of Use: https://policies.google.com/terms
* Privacy Policy: https://policies.google.com/privacy

When reCAPTCHA is enabled in form settings, this plugin will:
- Load the reCAPTCHA JavaScript library from Google's servers (https://www.google.com/recaptcha/api.js)
- Send form submission data to Google for spam analysis
- Include user's IP address and browser information in the request

reCAPTCHA is disabled by default and requires explicit activation by the site administrator.

This plugin also optionally integrates with Cloudflare Turnstile for spam protection:

* Service: Cloudflare Turnstile
* API Documentation: https://developers.cloudflare.com/turnstile/
* Terms of Use: https://www.cloudflare.com/terms/
* Privacy Policy: https://www.cloudflare.com/privacy/

When Turnstile is enabled in form settings, this plugin will:
- Load the Turnstile JavaScript library from Cloudflare's servers
- Send form submission tokens to Cloudflare for verification
- Include user's IP address and browser information in the request

Turnstile is disabled by default and requires explicit activation by the site administrator.

This plugin also uses the zipcloud postal code lookup API to auto-fill Japanese addresses when a user enters a postal code in a Postal Code or Address (Japan) field:

* Service: zipcloud (郵便番号検索API)
* API Endpoint: https://zipcloud.ibsnet.co.jp/api/search
* Service Information: https://zipcloud.ibsnet.co.jp/doc/api
* Terms of Use: https://zipcloud.ibsnet.co.jp/rule/api

When the user types a 7-digit postal code, the form's JavaScript sends a request from the visitor's browser to the zipcloud API to retrieve the corresponding prefecture / city / town information and populate the address fields. Only the postal code is sent — no personal information is transmitted. The lookup runs only when a Postal Code or Address (Japan) field is present in the form, and runs in the visitor's browser, not from the server.

= Third Party Resources =

This plugin includes a list of disposable email domains for spam protection:

* Source: [disposable-email-domains](https://github.com/disposable-email-domains/disposable-email-domains)
* License: CC0 1.0 Universal (Public Domain)
* Usage: Emails from disposable/temporary email services are automatically blocked during form submission

The list is bundled with the plugin and does not make any external requests.

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

== Screenshots ==

1. Intuitive modal UI for easy and clear field configuration.
2. Freely customizable confirmation screen layout.
3. Email notification settings for admin and auto-reply.
4. Submission data management with CSV export support.
5. Embed forms on external sites via iframe or JavaScript.

== Changelog ==

= 1.1.0 =
* New: Added a Contact Form 7 style form selector block. From the block inserter, choose "Form Plant" and pick a form from the dropdown to embed it.
* New: Added REST API endpoint `GET /form-plant/v1/forms` (capability: edit_posts) used by the block editor to list available forms.
* New: Added per-part required validation and custom error messages for the Name (parts) field.
* New: Unified form design settings; multiple custom CSS files can now be uploaded per form.
* New: Added design type presets and a customizable required-field marker.
* New: Split the address template into a Japanese locale version and an international version.
* New: Display a save-completion notice on the settings page.

= 1.0.0 =
* Initial release
