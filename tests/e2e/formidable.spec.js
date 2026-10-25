import { expect, test } from '@playwright/test';
import { randomUUID } from 'node:crypto';

async function submitFormidable(page, submit) {
  const responsePromise = page.waitForResponse((response) =>
    response.url().endsWith('/wp-admin/admin-ajax.php') &&
    response.request().method() === 'POST' &&
    response.request().postData()?.includes('action=frm_entries_create'),
  );
  await submit.click();
  const response = await responsePromise;
  expect(response.ok()).toBe(true);
  return response.json();
}

test('Formidable Forms rejects submission with unsolved Private Captcha even if Submit is re-enabled', async ({ page }) => {
  await page.goto('/?pagename=formidable-e2e');
  const form = page.locator('form.frm-show-form');
  const widget = form.locator('.private-captcha');
  const submit = form.getByRole('button', { name: 'Submit', exact: true });

  await form.getByLabel('Email', { exact: false }).fill('visitor@example.org');
  await expect(widget.getByText('Click to verify', { exact: true })).toBeVisible();
  await expect(submit).toBeDisabled();
  expect(await widget.evaluate((element) => element._privateCaptcha.solution())).toBeFalsy();

  // Bypass only the button guard; leave the CAPTCHA unsolved.
  await submit.evaluate((button) => { button.disabled = false; });
  expect(await submitFormidable(page, submit)).toMatchObject({
    errors: { private_captcha: 'Captcha verification failed. Please try again.' },
  });
  await expect(form.getByText('Captcha verification failed. Please try again.', { exact: true })).toBeVisible();
  await expect(page.locator('.frm_message')).toHaveCount(0);
  await expect(submit).toBeDisabled();
});

test('Formidable Forms submits with Private Captcha and resets the solution', async ({ page }) => {
  await page.goto('/?pagename=formidable-e2e');
  const form = page.locator('form.frm-show-form');
  const widget = form.locator('.private-captcha');
  const submit = form.getByRole('button', { name: 'Submit', exact: true });

  await expect(form).toHaveCount(1);
  await expect(widget).toHaveCount(1);
  await expect(widget).toHaveAttribute('data-theme', 'light');
  await expect(widget).toHaveAttribute('data-start-mode', 'auto');
  await expect(submit).toBeDisabled();
  // Formidable stores entries and rejects duplicates, including across test runs.
  await form.getByLabel('Email', { exact: false }).fill(`visitor+${randomUUID()}@example.org`);
  await widget.getByText('Click to verify', { exact: true }).click();
  await expect(submit).toBeEnabled({ timeout: 60_000 });

  // Formidable replaces the form on success; retain its widget to check reset.
  const captcha = await page.evaluateHandle(() => window.privateCaptcha.autoWidget);
  expect(await captcha.evaluate((instance) => instance.solution())).toBeTruthy();
  const result = await submitFormidable(page, submit);
  expect(result.content).toContain('Formidable e2e submission received.');
  await expect(page.locator('.frm_message')).toHaveText('Formidable e2e submission received.');
  await expect.poll(() => captcha.evaluate((instance) => instance.solution())).toBeFalsy();
  await captcha.dispose();
});

for (const validation of [
  { name: 'an empty required email', value: '', message: 'Email cannot be blank.' },
  { name: 'an invalid email address', value: 'not-an-email', message: 'Enter a valid email address, like name@example.com' },
]) {
  test(`Formidable Forms resets solved Private Captcha after the server rejects ${validation.name}`, async ({ page }) => {
    await page.goto('/?pagename=formidable-e2e');
    const form = page.locator('form.frm-show-form');
    const email = form.getByLabel('Email', { exact: false });
    const widget = form.locator('.private-captcha');
    const submit = form.getByRole('button', { name: 'Submit', exact: true });
    const address = `visitor+${randomUUID()}@example.org`;

    // Bypass native email checks so the real server validates the invalid value.
    await form.evaluate((element) => { element.noValidate = true; });
    await email.fill(address);
    await widget.getByText('Click to verify', { exact: true }).click();
    await expect(submit).toBeEnabled({ timeout: 60_000 });
    await email.fill(validation.value);
    expect(await widget.evaluate((element) => element._privateCaptcha.solution())).toBeTruthy();
    const result = await submitFormidable(page, submit);

    expect(Object.values(result.errors)).toContain(validation.message);
    await expect(form.locator('.frm_error')).toHaveText(validation.message);
    await expect(form.locator('.frm_error')).toBeVisible();
    await expect.poll(() => widget.evaluate((element) => element._privateCaptcha.solution())).toBeFalsy();
    await expect(widget.getByText('Click to verify', { exact: true })).toBeVisible();
    await expect(submit).toBeDisabled();

    await email.fill(address);
    // Blur clears the old field error before its layout shift can interrupt the CAPTCHA click.
    await email.press('Tab');
    await widget.getByText('Click to verify', { exact: true }).click();
    await expect(submit).toBeEnabled({ timeout: 60_000 });
    const retry = await submitFormidable(page, submit);
    expect(retry.errors).toEqual([]);
    expect(retry).toMatchObject({ content: expect.stringContaining('Formidable e2e submission received.') });
    await expect(page.locator('.frm_message')).toHaveText('Formidable e2e submission received.');
  });
}
