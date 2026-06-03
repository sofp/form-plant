=== Form Plant ===
Contributors: reiji-sato
Tags: contact form, confirmation, mw wp form, csv export, recaptcha
Requires at least: 6.0
Tested up to: 7.0
Stable tag: 1.2.1
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A WordPress contact form plugin with confirmation screen, submission storage, layered spam protection, and Japanese postal code auto-fill.

== Description ==

Form Plant is a WordPress contact form plugin built around three pillars: practical defaults, layered spam protection, and strong Japanese-locale support.

**Ready out of the box** — Confirmation screen, submission storage, and CSV export are standard features, not paid add-ons. Publish a working form in minutes.

**Spam protection in depth** — Combine honeypot, time-based validation, IP rate limiting, disposable email blocking, Google reCAPTCHA (v2/v3), and Cloudflare Turnstile. Start with the lightweight defaults that work without any account setup; add reCAPTCHA or Turnstile only when you need them.

**Built for Japanese sites** — Postal code lookup with address auto-fill, per-part name/kana validation, and Japan-specific address templates. Moving from MW WP Form? A built-in migration tool imports your existing forms — fields, validation rules, and mail settings — so you can switch without rebuilding from scratch.

Many form plugins require extra extensions for confirmation pages or data management. Form Plant includes these essentials from the start.

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
* MW WP Form migration tool — convert existing MW WP Form forms (fields, validation rules, admin and auto-reply mail settings, and merge tags) into Form Plant forms, with a warning report for items that need manual review (shown when MW WP Form is active)
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
6. One-click migration from MW WP Form, with a report of converted fields and items that need manual review.

== Changelog ==

= 1.2.1 =
* Important: Forms now respect their publish status. Private, draft, and pending forms are no longer shown to or submittable by visitors; only users who can edit a form can preview and test-submit it. Published forms are unaffected. To restore the previous behavior, use the new `fplant_form_is_viewable` / `fplant_form_is_submittable` filters.
* New: The forms list now shows a colored status badge (published, private, draft, pending) next to each form title.
* Change: Migrating from MW WP Form now creates the new form as published with Form Plant's standard design, so it works right after placing the shortcode. The MW WP Form layout is still saved as an HTML template but is disabled by default; enable it from the layout settings to use the original layout.
* Fix: Forms embedded via iframe could not be submitted when the time-based spam check was enabled, because the form timestamp field was missing from the iframe template.
* Developer: Added `fplant_form_is_viewable`, `fplant_form_is_submittable`, and `fplant_preview_notice` filters to control front-end visibility, submission, and the editor preview notice per form.

= 1.2.0 =
* New: MW WP Form migration tool. Convert existing MW WP Form forms — fields, validation rules, admin and auto-reply mail settings, and merge tags — into Form Plant forms, with a warning report for items that need manual review. Available under Form Plant → Tools while MW WP Form is active.
* New: Custom Mail Tag field type. Embed a dynamic value (supplied via the `fplant_custom_mail_tag_value_*` filter) into the form, the saved submission, and the email body.
* New: Added rows/cols/maxlength settings for textarea fields and size/maxlength settings for email, url, and password fields in the form editor.
* Fix: Custom Mail Tag fields are now rendered on the front end so their value reaches the submission data and email.
* Fix: Validation errors are now displayed on forms that use an HTML template without a confirmation screen.
* Developer: Added filters and actions for MW WP Form-equivalent extensibility — dynamic field values/choices, custom validation, email subject/body/recipient customization and conditional skipping, post-submission integration, redirect and completion-page control, attachment save path/filename, custom form settings, and CSV export encoding.

= 1.1.1 =
* Fix: Changed the source strings in `block.json` (block description and keywords) from Japanese to English so that English-locale sites display the correct text.

= 1.1.0 =
* New: Layered spam protection — added time-based validation, IP rate limiting, disposable email domain blocking, Google reCAPTCHA v2 Checkbox, and Cloudflare Turnstile (in addition to existing honeypot and reCAPTCHA v3).
* New: Postal code lookup that auto-fills address fields for the Japanese locale, using the zipcloud API.
* New: JavaScript hook API (`window.fplant.addValidator`, `removeValidator`, and form lifecycle events) for custom validation and behavior.
* New: Password field type with optional strength meter.
* New: Form settings export/import for migration and backup.
* New: Added a Contact Form 7 style form selector block. From the block inserter, choose "Form Plant" and pick a form from the dropdown to embed it.
* New: Added REST API endpoint `GET /form-plant/v1/forms` (capability: edit_posts) used by the block editor to list available forms.
* New: Per-part required validation and custom error messages for the Name (parts) field.
* New: Unified form design settings; multiple custom CSS files can now be uploaded per form.
* New: Added design type presets and a customizable required-field marker.
* New: Split the address template into a Japanese locale version and an international version.
* New: Display a save-completion notice on the settings page.

= 1.0.0 =
* Initial release
