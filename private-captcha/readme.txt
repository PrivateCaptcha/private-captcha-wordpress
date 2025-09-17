=== Private Captcha for WordPress ===
Contributors: privatecaptcha
Tags: captcha, security, spam, protection, private
Requires at least: 5.6
Tested up to: 6.8
Requires PHP: 8.2
Stable tag: 1.0.0
License: MIT
License URI: https://opensource.org/licenses/MIT

Integrates Private Captcha with WordPress forms for enhanced security and spam protection.

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

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/private-captcha/` or install through WordPress admin
2. Activate the plugin through the 'Plugins' menu
3. Go to Settings → Private Captcha
4. Add your API Key and Site Key from [Private Captcha Portal](https://portal.privatecaptcha.com)
5. Enable desired form integrations
6. Customize widget appearance as needed

== Frequently Asked Questions ==

= Do I need a Private Captcha account? =

Yes, you need to create a free account at [Private Captcha Portal](https://portal.privatecaptcha.com/signup) to get your API Key and Site Key.

= What if I get locked out of my site? =

Use WP-CLI commands to recover access:
* `wp private-captcha update-api-key "your-new-key"`
* `wp private-captcha disable-login`

== Changelog ==

= 1.0.0 =
* Initial release
* Support for login, registration, password reset, and comment forms
* EU isolation and custom domain support
* WP-CLI emergency commands
