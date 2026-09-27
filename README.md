# Private Captcha WordPress Plugin

![CI](https://github.com/PrivateCaptcha/private-captcha-wordpress/actions/workflows/ci.yaml/badge.svg)

## Features

- **Form Protection**: Standard forms (login, registration, password reset, comments) and select custom plugins
- **Flexible Configuration**: Theme, language, start mode, and custom styling options
- **EU Compliance**: Support for EU-only endpoints and custom domains
- **WP-CLI Commands**: Emergency management tools for API key updates and login bypass

## Installation

> <mark>Check detailed step-by-step setup instructions [here](https://docs.privatecaptcha.com/docs/integrations/wordpress/).</mark>

1. Install and activate the plugin
2. Go to **Settings → Private Captcha**
3. Add your **API Key** and **Site Key** from [Private Captcha Portal](https://portal.privatecaptcha.com)
4. Enable desired form integrations

## Supported Forms

- WordPress (Login, Registrations, Password reset, Comment forms for logged-in/guests)
- WPForms
- Contact Form 7 (use `[privatecaptcha]` tag)
- Gravity Forms
- Elementor Pro (add "Private Captcha" field to form)
- WooCommerce (Login, Registration, Password reset, Checkout guest/logged-in)
- Fluent Forms
- Forminator
- Formidable
- Ultimate Member (Login, Registration, Password reset)
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
- PHP 8.1+
- [Private Captcha account](https://portal.privatecaptcha.com/signup)

## Local development with Docker

With Docker Compose and `make`, you can run any target in `private-captcha/Makefile` without installing PHP or Composer on your computer. From the repository root:

```bash
make docker-plugin TARGET=install-dev
make docker-plugin TARGET=check
make docker-plugin TARGET=wpcs-fix
```

Replace `TARGET` with any plugin Makefile target (for example, `install`, `lint`, `analyze`, `validate`, or `clean`). Omitting `TARGET` runs `check`. Dependencies installed by Composer are written to `private-captcha/vendor/` on your computer, so they persist between commands. The tools container runs independently of the WordPress stack; `make run-docker` is only needed to run WordPress locally.

## License

MIT License
