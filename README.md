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

## Form integration end-to-end tests

The minimal Playwright suite uses real WordPress, WPForms Lite 2.0.2.1, Contact Form 7 6.1.7, and WooCommerce 10.9.4 in Docker, with Playwright/Chromium running on the host. Its separate Compose project (`private-captcha-wordpress-e2e`), volumes, and port (`18081`) allow it to run alongside the manual-testing stack started by `make run-docker` on port `18080`. WooCommerce 10.9.4 supports the stack's WordPress 6.9.

WPForms and Contact Form 7 each have one fixture page with one form containing a required Email field:

- WPForms: **http://localhost:18081/**
- Contact Form 7: **http://localhost:18081/?pagename=contact-form-7-e2e**

Private Captcha uses its default settings with both form integrations and WooCommerce guest checkout enabled. Both forms submit via AJAX. WPForms has email notifications disabled; CF7 uses [`skip_mail: on`](https://contactform7.com/additional-settings/) to skip email delivery while retaining validation and CAPTCHA verification, so no SMTP setup is needed. Provisioning updates the same forms and pages when repeated and reinstalls WPForms Lite 2.0.2.1 if it is missing or a different version is installed. Run `make run-e2e` again to install and provision a newly added integration.

WooCommerce fixtures include:

- Product: **http://localhost:18081/?product=woocommerce-e2e-product**
- Block checkout: **http://localhost:18081/?pagename=checkout**
- Legacy checkout: **http://localhost:18081/?pagename=woocommerce-legacy-checkout-e2e**

The store uses one $10 virtual product, no shipping or taxes, guest checkout, and the built-in [Direct bank transfer (BACS)](https://woocommerce.com/document/bacs/) payment method. Orders are accepted pending payment (`on-hold`); no money is collected and no bank account, card, or external payment service is needed. WooCommerce order emails are disabled. Each browser test starts with its own empty cart and adds the product through the storefront.

### Setup

Requires Docker Compose with `--wait` support, Node.js 20+, and the plugin's Composer dependencies. If `private-captcha/vendor/` is missing, install them once with `make docker-plugin TARGET=install-dev`.

1. Copy the separate e2e environment file:

   ```bash
   cp docker/.env.e2e.example docker/.env.e2e
   ```

2. Set `PC_API_KEY` and `PC_SITEKEY` in `docker/.env.e2e` using a real development property with **Allow localhost** enabled. A low, constant challenge difficulty keeps the test fast. Both browser-side solving and server-side verification use the real Private Captcha service and require internet access.

   The [public dummy sitekey](https://docs.privatecaptcha.com/docs/reference/testing/) is currently unsuitable: its verification code `10` is rejected by the PHP SDK's `isOK()` method. Provisioning rejects that sitekey explicitly.

3. Install the host-side test dependencies and start/provision the e2e stack:

   ```bash
   npm ci
   PLAYWRIGHT_BROWSERS_PATH=0 npx playwright install chromium
   make run-e2e
   ```

### Run

```bash
make test-e2e
```

The suite has eight tests. WPForms and Contact Form 7 each cover:

- Successful AJAX submission and CAPTCHA reset after confirmation.
- An empty required Email field after CAPTCHA is already solved.
- An invalid email address after CAPTCHA is already solved.

The validation-error cases check that the error is displayed, CAPTCHA resets and disables submission, and correcting the field and completing a fresh CAPTCHA allows a successful retry. CF7's success test also verifies that the Email field clears and a second submission succeeds on the same page without reloading.

WooCommerce has one happy-path test for the block checkout and one for the legacy shortcode checkout. Both fill billing details, solve a real CAPTCHA, verify the solution is sent with checkout, and assert successful order creation and the order confirmation page using bank transfer. The block test also checks the Store API order status and payment result. Checkout responses are captured from the real server before being forwarded unchanged to the browser, avoiding a Chrome response-body race during the confirmation redirect.

Default auto mode prepares the challenge when the form gains focus, but the visible widget still requires a "Click to verify" click. WPForms replaces the form with its confirmation message on success; the tests retain the documented widget object to check the reset after that replacement. Screenshots and Playwright traces are retained in `test-results/` on failure.

Chromium is installed inside the ignored `node_modules/` directory rather than a shared browser cache. The test command sets `PLAYWRIGHT_BROWSERS_PATH=0` to use that installation.

The e2e admin login is `admin` / `e2e-password`. To remove the e2e containers and their data:

```bash
make stop-e2e
```

### CI

On trusted pushes and same-repository pull requests, CI runs all integration suites after all PHP validation jobs succeed and before packaging. An e2e failure blocks packaging and releases. Failure screenshots, traces, and container logs are uploaded as the `wpforms-e2e-failure` artifact; the test stack is always cleaned up.

Configure these GitHub Actions secrets in the **`E2E tests` environment** using a real development property with **Allow localhost** enabled:

- `PC_E2E_API_KEY` — the development API key.
- `PC_E2E_SITEKEY` — the development property's sitekey.

Fork pull requests and Dependabot runs skip the secret-backed e2e job and can produce a package artifact after PHP validation succeeds. Packaging remains blocked by failed PHP validation, failed e2e tests, or cancellation. Trusted runs without the configured secrets fail the e2e credential check and cannot proceed to packaging.

## License

MIT License
