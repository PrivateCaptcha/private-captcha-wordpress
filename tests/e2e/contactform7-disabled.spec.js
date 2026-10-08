import { expect, test } from '@playwright/test';

// Toggle the Contact Form 7 integration setting via WP-CLI inside the e2e
// WordPress container. `value` is '1' to enable the integration, '0' to disable it.
async function setCf7Enabled(value) {
  const { execSync } = await import('node:child_process');
  execSync(
    `docker compose -p private-captcha-wordpress-e2e -f docker/docker-compose.e2e.yml ` +
      `run --rm --no-deps --entrypoint sh cli -c ` +
      `'wp --user=admin option patch update private_captcha_settings contactform7_enable ${value}'`,
    { stdio: 'ignore' },
  );
}

test.afterAll(async () => {
  // Leave the integration enabled so the rest of the e2e suite stays green.
  await setCf7Enabled('1');
});

test('Contact Form 7 collapses [privatecaptcha] to nothing when the integration is disabled', async ({ page }) => {
  await setCf7Enabled('0');

  await page.goto('/?pagename=contact-form-7-e2e');

  // The CF7 form itself still renders.
  await expect(page.locator('form.wpcf7-form')).toHaveCount(1);

  // The literal [privatecaptcha] token must NOT appear in the rendered page
  // (it should be neutralized to an empty string by the disabled-integration
  // form-tag handler registered via register_neutralization()).
  await expect(page.locator('body')).not.toContainText('[privatecaptcha]');

  // The Private Captcha widget must NOT render when disabled.
  await expect(page.locator('.private-captcha')).toHaveCount(0);
});

test('Contact Form 7 re-renders the widget after the integration is re-enabled', async ({ page }) => {
  await setCf7Enabled('1');

  await page.goto('/?pagename=contact-form-7-e2e');

  await expect(page.locator('form.wpcf7-form')).toHaveCount(1);
  const widget = page.locator('form.wpcf7-form .private-captcha');
  await expect(widget).toHaveCount(1);
  // No literal tag text leaks through on the enabled path either.
  await expect(page.locator('body')).not.toContainText('[privatecaptcha]');
});
