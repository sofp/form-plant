=== Form Plant ===
Contributors: reiji-sato
Tags: form, contact form, custom form, email, inquiry
Requires at least: 6.0
Tested up to: 6.9
Stable tag: 1.0.0
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
* Email notifications (admin notification and auto-reply)
* Custom HTML template support
* Confirmation screen before submission
* Custom validation messages
* Post-submission actions (message / custom HTML / redirect)
* Two types of date input (calendar / dropdown)
* Honeypot and reCAPTCHA v3 spam protection
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

== Screenshots ==

1. Intuitive modal UI for easy and clear field configuration.
2. Freely customizable confirmation screen layout.
3. Email notification settings for admin and auto-reply.
4. Submission data management with CSV export support.
5. Embed forms on external sites via iframe or JavaScript.

== Changelog ==

= 1.0.0 =
* Initial release
