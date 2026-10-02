import { expect, test } from '@playwright/test';

test('WPForms submits with Private Captcha and resets the solution', async ({ page }) => {
  await page.goto('/');

  const form = page.locator('form.wpforms-form');
  const widget = form.locator('.private-captcha');
  const submit = form.getByRole('button', { name: 'Submit', exact: true });

  await expect(form).toHaveCount(1);
  await expect(widget).toHaveCount(1);
  await expect(widget).toBeVisible();
  await expect(widget).toHaveAttribute('data-theme', 'light');
  await expect(widget).toHaveAttribute('data-start-mode', 'auto');
  await expect(submit).toBeDisabled();

  // Auto mode prepares the challenge on focus; the visible widget still needs a click.
  await form.getByLabel('Email', { exact: false }).fill('visitor@example.org');
  await widget.getByText('Click to verify', { exact: true }).click();
  await expect(submit).toBeEnabled({ timeout: 60_000 });

  // Keep the documented widget object: WPForms replaces the form with its confirmation.
  // Source: https://docs.privatecaptcha.com/docs/reference/captcha-object/
  const captcha = await page.evaluateHandle(() => window.privateCaptcha.autoWidget);
  expect(await captcha.evaluate((instance) => instance.solution())).toBeTruthy();

  const responsePromise = page.waitForResponse((response) =>
    response.url().endsWith('/wp-admin/admin-ajax.php') &&
    response.request().method() === 'POST' &&
    response.request().postData()?.includes('wpforms_submit'),
  );
  await submit.click();
  const response = await responsePromise;

  expect(response.ok()).toBe(true);
  expect(await response.json()).toMatchObject({ success: true });
  await expect(page.getByText('Thanks for contacting us! We will be in touch with you shortly.', { exact: true })).toBeVisible();
  await expect.poll(() => captcha.evaluate((instance) => instance.solution())).toBeFalsy();
  await captcha.dispose();
});

for (const validation of [
  { name: 'an empty required email', value: '', message: 'This field is required.' },
  { name: 'an invalid email address', value: 'not-an-email', message: 'Please enter a valid email address.' },
]) {
  test(`WPForms resets solved Private Captcha after ${validation.name}`, async ({ page }) => {
    await page.goto('/');

    const form = page.locator('form.wpforms-form');
    const email = form.getByLabel('Email', { exact: false });
    const widget = form.locator('.private-captcha');
    const submit = form.getByRole('button', { name: 'Submit', exact: true });

    await email.fill('visitor@example.org');
    await widget.getByText('Click to verify', { exact: true }).click();
    await expect(submit).toBeEnabled({ timeout: 60_000 });
    const captcha = await page.evaluateHandle(() => window.privateCaptcha.autoWidget);

    // The CAPTCHA must still be solved when the invalid submission is attempted.
    await email.fill(validation.value);
    // Blur validation can move Submit when its error message is inserted.
    await email.press('Tab');
    expect(await captcha.evaluate((instance) => instance.solution())).toBeTruthy();
    await submit.click();

    await expect(form.getByText(validation.message, { exact: true })).toBeVisible();
    await expect.poll(() => captcha.evaluate((instance) => instance.solution())).toBeFalsy();
    await expect(widget.getByText('Click to verify', { exact: true })).toBeVisible();
    await expect(submit).toBeDisabled();

    // Correcting the field and completing a fresh CAPTCHA must allow a successful retry.
    await email.fill('visitor@example.org');
    await email.press('Tab');
    await widget.getByText('Click to verify', { exact: true }).click();
    await expect(submit).toBeEnabled({ timeout: 60_000 });
    const responsePromise = page.waitForResponse((response) =>
      response.url().endsWith('/wp-admin/admin-ajax.php') &&
      response.request().method() === 'POST' &&
      response.request().postData()?.includes('wpforms_submit'),
    );
    await submit.click();
    const response = await responsePromise;

    expect(response.ok()).toBe(true);
    expect(await response.json()).toMatchObject({ success: true });
    await expect(page.getByText('Thanks for contacting us! We will be in touch with you shortly.', { exact: true })).toBeVisible();
    await captcha.dispose();
  });
}
