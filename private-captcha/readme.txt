=== Private Captcha ===
Contributors: ribtoks
Tags: captcha, security, spam, protection, private
Requires at least: 5.6
Tested up to: 6.8
Requires PHP: 8.2
Stable tag: 1.0.8
License: MIT
License URI: https://opensource.org/licenses/MIT

Private Captcha protects your WordPress website from spam and abuse with a privacy-first, independent CAPTCHA solution made in EU.

== Description ==

Private Captcha WordPress Plugin integrates Private Captcha with your WordPress site to protect forms from spam and abuse.

**Features:**
* Protect login, registration, password reset, and comment forms
* Flexible widget configuration (theme, language, start mode)
* EU compliance with EU-only endpoints and custom domains
* WP-CLI commands for emergency management
* No tracking, privacy-focused captcha solution

**Supported Forms:**
* WordPress Login Form
* WordPress Registration Form  
* WordPress Password Reset Form
* WordPress Comment Forms (logged-in and guest users)
* WPForms
* Contact Form 7

More forms (including popular plugins) are currently in progress of being added.


== Installation ==

1. Upload the plugin files to `/wp-content/plugins/private-captcha/` or install through WordPress admin
2. Activate the plugin through the 'Plugins' menu
3. Go to Settings → Private Captcha
4. Add your API Key and Site Key from [Private Captcha Portal](https://portal.privatecaptcha.com)
5. Enable desired form integrations
6. Customize widget appearance as needed

== External services ==

This plugin uses [Private Captcha](https://privatecaptcha.com) API to request and verify captcha challenges and their solutions, when protection is enabled on user-configured forms.

When solving of the captcha widget challenge begins, a new challenge is requested from Private Captcha API. When submitting the relevant form (e.g. Comment Form or Login Form), challenge solution is sent to Private Captcha API.

During requesting challenges or verifying solutions, no Personal Data (within meaning of Art. 4 (1) GDPR) is neither collected, nor sent. Check all relevant information in the Private Captcha's [Privacy Policy](https://privatecaptcha.com/legal/privacy/).

== Frequently Asked Questions ==

= Do I need a Private Captcha account? =

Yes, you need to create a free account at [Private Captcha Portal](https://portal.privatecaptcha.com/signup) to get your API Key and Site Key.

= What if I get locked out of my site? =

Use WP-CLI commands to recover access:
* `wp private-captcha update-api-key "your-new-key"`
* `wp private-captcha disable-login`

== Changelog ==

= 1.0.8 =
* Add missing file

= 1.0.7 =
* Add Contact Form 7 support

= 1.0.6 =
* Add WPForms support

= 1.0.5 =
* Fix settings test

= 1.0.4 =
* Cleanup distribution package

= 1.0.3 =
* Updated contributors list

= 1.0.2 =
* Fix cosmetic review comments

= 1.0.1 =
* Fix cosmetic review issues

= 1.0.0 =
* Initial release
* Support for login, registration, password reset, and comment forms
* EU isolation and custom domain support
* WP-CLI emergency commands
