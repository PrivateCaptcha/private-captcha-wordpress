import { expect, test } from '@playwright/test';

async function submitContactForm(page, submit) {
  const responsePromise = page.waitForResponse((response) =>
    response.request().method() === 'POST' &&
    /\/contact-form-7\/v1\/contact-forms\/\d+\/feedback(?:[?&]|$)/.test(decodeURIComponent(response.url())),
  );
  await submit.click();
  const response = await responsePromise;
  expect(response.ok()).toBe(true);
  return response.json();
}

test('Contact Form 7 submits with Private Captcha and resets for a second submission', async ({ page }) => {
  await page.goto('/?pagename=contact-form-7-e2e');

  const form = page.locator('form.wpcf7-form');
  const email = form.getByLabel('Email', { exact: false });
  const widget = form.locator('.private-captcha');
  const submit = form.getByRole('button', { name: 'Submit', exact: true });

  await expect(form).toHaveCount(1);
  await expect(widget).toHaveCount(1);
  await expect(widget).toBeVisible();
  await expect(widget).toHaveAttribute('data-theme', 'light');
  await expect(widget).toHaveAttribute('data-start-mode', 'auto');
  await expect(submit).toBeDisabled();

  // CF7 keeps the form on screen after success, so exercise reuse without reloading.
  for (let attempt = 0; attempt < 2; attempt++) {
    await email.fill('visitor@example.org');
    await widget.getByText('Click to verify', { exact: true }).click();
    await expect(submit).toBeEnabled({ timeout: 60_000 });
    expect(await widget.evaluate((element) => !!element._privateCaptcha.solution())).toBe(true);

    expect(await submitContactForm(page, submit)).toMatchObject({ status: 'mail_sent' });
    await expect(form.locator('.wpcf7-response-output')).toHaveText('Thank you for your message. It has been sent.');
    // CF7 asynchronously refills the form before its final reset event; wait before reuse.
    await expect(form).toHaveAttribute('data-status', 'sent');
    await expect(email).toHaveValue('');
    await expect.poll(() => widget.evaluate((element) => !!element._privateCaptcha.solution())).toBe(false);
    await expect(widget.getByText('Click to verify', { exact: true })).toBeVisible();
    await expect(submit).toBeDisabled();
  }
});

for (const validation of [
  { name: 'an empty required email', value: '', message: 'Please fill out this field.' },
  { name: 'an invalid email address', value: 'not-an-email', message: 'Please enter an email address.' },
]) {
  test(`Contact Form 7 resets solved Private Captcha after ${validation.name}`, async ({ page }) => {
    await page.goto('/?pagename=contact-form-7-e2e');

    const form = page.locator('form.wpcf7-form');
    const email = form.getByLabel('Email', { exact: false });
    const widget = form.locator('.private-captcha');
    const submit = form.getByRole('button', { name: 'Submit', exact: true });

    await email.fill('visitor@example.org');
    await widget.getByText('Click to verify', { exact: true }).click();
    await expect(submit).toBeEnabled({ timeout: 60_000 });

    await email.fill(validation.value);
    await email.press('Tab');
    expect(await widget.evaluate((element) => !!element._privateCaptcha.solution())).toBe(true);
    await submit.click();

    await expect(form.getByText(validation.message, { exact: true })).toBeVisible();
    await expect.poll(() => widget.evaluate((element) => !!element._privateCaptcha.solution())).toBe(false);
    await expect(widget.getByText('Click to verify', { exact: true })).toBeVisible();
    await expect(submit).toBeDisabled();

    await email.fill('visitor@example.org');
    await email.press('Tab');
    await widget.getByText('Click to verify', { exact: true }).click();
    await expect(submit).toBeEnabled({ timeout: 60_000 });
    expect(await submitContactForm(page, submit)).toMatchObject({ status: 'mail_sent' });
    await expect(form.locator('.wpcf7-response-output')).toHaveText('Thank you for your message. It has been sent.');
    await expect.poll(() => widget.evaluate((element) => !!element._privateCaptcha.solution())).toBe(false);
  });
}
