# Private Captcha WordPress Plugin

![CI](https://github.com/PrivateCaptcha/private-captcha-wordpress/actions/workflows/ci.yaml/badge.svg)

## Features

- **Form Protection**: Standard forms (login, registration, password reset, comments) and select custom plugins
- **Flexible Configuration**: Theme, language, start mode, and custom styling options
- **EU Compliance**: Support for EU-only endpoints and custom domains
- **WP-CLI Commands**: Emergency management tools for API key updates and login bypass

## Installation

> Check detailed step-by-step setup instructions [here](https://docs.privatecaptcha.com/docs/integrations/wordpress/).

1. Install and activate the plugin
2. Go to **Settings → Private Captcha**
3. Add your **API Key** and **Site Key** from [Private Captcha Portal](https://portal.privatecaptcha.com)
4. Enable desired form integrations

## Supported Forms

- WordPress Login Form
- WordPress Registration Form
- WordPress Password Reset Form
- WordPress Comment Forms (logged-in and guest users)
- WPForms
- _More forms support (including popular plugins) are currently **in progress**_

## WP-CLI Commands

Emergency management when locked out:

```bash
# Update API key
wp private-captcha update-api-key "your-new-api-key"

# Disable login captcha (emergency use)
wp private-captcha disable-login
```

## Requirements

- WordPress 5.6+
- PHP 8.2+
- [Private Captcha account](https://portal.privatecaptcha.com/signup)

## License

MIT License
