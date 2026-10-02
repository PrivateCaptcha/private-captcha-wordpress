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

## WPForms end-to-end tests

The minimal Playwright suite uses real WordPress and WPForms Lite 2.0.2.1 in Docker, with Playwright/Chromium running on the host. Its separate Compose project (`private-captcha-wordpress-e2e`), volumes, and port (`18081`) allow it to run alongside the manual-testing stack started by `make run-docker` on port `18080`.

The fixture is one page at **http://localhost:18081/** containing one form with a required Email field. Private Captcha uses its default settings with WPForms enabled. The form submits via AJAX and has email notifications disabled, so no SMTP setup is needed. Provisioning updates the same form and page when repeated.

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

The suite covers:

- Successful AJAX submission and CAPTCHA reset after confirmation.
- An empty required Email field after CAPTCHA is already solved.
- An invalid email address after CAPTCHA is already solved.

Both validation-error cases check that the error is displayed, CAPTCHA resets and disables submission, and correcting the field and completing a fresh CAPTCHA allows a successful retry.

Default auto mode prepares the challenge when the form gains focus, but the visible widget still requires a "Click to verify" click. WPForms replaces the form with its confirmation message on success; the tests retain the documented widget object to check the reset after that replacement. Screenshots and Playwright traces are retained in `test-results/` on failure.

Chromium is installed inside the ignored `node_modules/` directory rather than a shared browser cache. The test command sets `PLAYWRIGHT_BROWSERS_PATH=0` to use that installation.

The e2e admin login is `admin` / `e2e-password`. To remove the e2e containers and their data:

```bash
make stop-e2e
```

### CI

CI runs the WPForms suite after all PHP validation jobs succeed and before packaging. An e2e failure blocks packaging and releases. Failure screenshots, traces, and container logs are uploaded as the `wpforms-e2e-failure` artifact; the test stack is always cleaned up.

Configure these GitHub Actions secrets in the **`e2e` environment** using a real development property with **Allow localhost** enabled:

- `PC_E2E_API_KEY` — the development API key.
- `PC_E2E_SITEKEY` — the development property's sitekey.

Runs without these secrets, including fork pull requests, fail the e2e credential check and cannot proceed to packaging.

## License

MIT License
