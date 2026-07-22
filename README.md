# Form Plant

[日本語版はこちら](README.ja.md)

A WordPress form plugin with built-in confirmation screen, submission data storage, and external site embedding — ready to use right out of the box.

## Overview

Form Plant is a WordPress form plugin that includes a confirmation screen, submission data storage, and CSV export as standard features — no add-ons required.

Many form plugins require extra extensions for confirmation pages or data management. Form Plant gives you these essentials from the start, so you can set up a fully functional contact form in minutes.

## Why Form Plant?

- **Confirmation screen included** — Let users review their input before submitting. No extra plugin needed.
- **Submission data storage** — All form entries are saved in the database and manageable from the admin panel.
- **CSV export** — Download submission data anytime for reporting or backup.
- **Embed on external sites** — Display your forms on any website via iframe or JavaScript snippet, not just within WordPress.
- **Webhooks** — Send submissions to Zapier, Make, or your own API as signed JSON, with delivery logging and automatic retry.
- **Migrate from MW WP Form** — Import your existing MW WP Form forms — fields, validation rules, and mail settings — with the built-in migration tool. No need to rebuild from scratch.
- **Quick setup** — An intuitive ACF-style accordion editor lets you build forms without touching code.
- **No-code design** — Adjust colors, sizes, and spacing from the admin screen with a live preview.
- **Flexible customization** — Custom HTML templates, validation messages, and post-submission actions give you full control when you need it.

## Features

- Intuitive ACF-style accordion UI for field configuration, with an icon-based field type picker
- Design adjustments with a live preview — customize colors, sizes, and spacing without CSS
- Per-field descriptions in three positions (below label / above input / below input, HTML allowed)
- Theme-context form preview with desktop / tablet / mobile switching
- Block editor (Gutenberg) integration — embed forms with the dedicated "Form Plant" block
- MW WP Form migration tool — convert existing forms, validation rules, and mail settings into Form Plant forms
- Email notifications (admin notification and auto-reply)
- Webhooks — send each submission as signed JSON (HMAC-SHA256) to external URLs, with automatic retry and per-submission delivery log
- Acceptance (consent) field with a linked label for privacy policy / terms agreement
- Custom HTML template support
- Confirmation screen before submission
- Custom validation messages
- Post-submission actions (message / custom HTML / redirect)
- Two types of date input (calendar / dropdown)
- Postal code lookup that auto-fills address fields (Japan)
- Layered spam protection: honeypot, time-based check, IP rate limit, disposable email blocking, Google reCAPTCHA v2/v3, Cloudflare Turnstile
- External site embedding via iframe / JavaScript
- Submission data storage and management
- CSV export of submission data
- File upload support

## Requirements

- WordPress 6.0 or higher
- PHP 8.0 or higher
- MySQL 5.7+ / MariaDB 10.3+

## Installation

1. Upload the `form-plant` folder to the `wp-content/plugins/` directory
2. Activate Form Plant from the "Plugins" menu in WordPress admin
3. Create a new form from the "Form Plant" menu

## Basic Usage

1. **Create a form** - Go to "Form Plant" → "Add New" to create a form
2. **Add fields** - Add form fields in the "Field Settings" tab
3. **Edit layout** - Customize the HTML template in the "Layout Editor" tab (optional)
4. **Configure settings** - Set up email notifications in the "Form Settings" tab
5. **Embed** - Insert the shortcode `[fplant id="FORM_ID"]` in a page or post

## License

GPL v2 or later

See [LICENSE.txt](LICENSE.txt) for details.

## Support

- GitHub Repository: https://github.com/sofp/form-plant
- User manual: https://www.sofplant.com/form-plant/
- WordPress.org: https://wordpress.org/plugins/form-plant/

## Developer Information

- **Author**: SOFPLANT
- **Version**: 1.4.0
