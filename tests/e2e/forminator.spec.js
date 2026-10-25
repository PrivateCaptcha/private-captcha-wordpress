import { expect, test } from '@playwright/test';

async function submitForminator(page, submit) {
  const responsePromise = page.waitForResponse((response) =>
    response.url().endsWith('/wp-admin/admin-ajax.php') &&
    response.request().method() === 'POST' &&
    response.request().postData()?.includes('forminator_submit_form_custom-forms'),
  );
  await submit.click();
  const response = await responsePromise;
  expect(response.ok()).toBe(true);
  return response.json();
}

test('Forminator rejects submission with unsolved Private Captcha even if Submit is re-enabled', async ({ page }) => {
  await page.goto('/?pagename=forminator-e2e');
  const form = page.locator('form.forminator-custom-form');
  const widget = form.locator('.private-captcha');
  const submit = form.getByRole('button', { name: 'Submit', exact: true });

  await form.getByLabel('Email', { exact: false }).fill('visitor@example.org');
  await expect(widget.getByText('Click to verify', { exact: true })).toBeVisible();
  await expect(submit).toBeDisabled();
  expect(await widget.evaluate((element) => element._privateCaptcha.solution())).toBeFalsy();

  // Bypass only the button guard; leave the CAPTCHA unsolved.
  await submit.evaluate((button) => { button.disabled = false; });
  expect(await submitForminator(page, submit)).toMatchObject({
    success: false,
    data: { errors: [{ 'private-captcha': 'Captcha verification failed. Please try again.' }] },
  });
  await expect(form.getByText('Captcha verification failed. Please try again.', { exact: true })).toBeVisible();
  await expect(form.locator('.forminator-response-message')).not.toHaveClass(/forminator-success/);
  await expect(submit).toBeDisabled();
});

test('Forminator submits with Private Captcha and resets for a second submission', async ({ page }) => {
  await page.goto('/?pagename=forminator-e2e');
  const form = page.locator('form.forminator-custom-form');
  const email = form.getByLabel('Email', { exact: false });
  const widget = form.locator('.private-captcha');
  const submit = form.getByRole('button', { name: 'Submit', exact: true });

  await expect(form).toHaveCount(1);
  await expect(widget).toHaveCount(1);
  await expect(widget).toHaveAttribute('data-theme', 'light');
  await expect(widget).toHaveAttribute('data-start-mode', 'auto');
  await expect(submit).toBeDisabled();

  for (let attempt = 0; attempt < 2; attempt++) {
    await email.fill('visitor@example.org');
    await widget.getByText('Click to verify', { exact: true }).click();
    await expect(submit).toBeEnabled({ timeout: 60_000 });
    expect(await widget.evaluate((element) => element._privateCaptcha.solution())).toBeTruthy();
    expect(await submitForminator(page, submit)).toMatchObject({ success: true });
    await expect(form.locator('.forminator-response-message')).toContainText('Forminator e2e submission received.');
    await expect(email).toHaveValue('');
    await expect.poll(() => widget.evaluate((element) => element._privateCaptcha.solution())).toBeFalsy();
    await expect(widget.getByText('Click to verify', { exact: true })).toBeVisible();
    await expect(submit).toBeDisabled();
  }
});

for (const validation of [
  { name: 'an empty required email', value: '', message: 'This field is required. Please input a valid email.' },
  { name: 'an invalid email address', value: 'not-an-email', message: 'This is not a valid email.' },
]) {
  test(`Forminator resets solved Private Captcha after ${validation.name}`, async ({ page }) => {
    await page.goto('/?pagename=forminator-e2e');
    const form = page.locator('form.forminator-custom-form');
    const email = form.getByLabel('Email', { exact: false });
    const widget = form.locator('.private-captcha');
    const submit = form.getByRole('button', { name: 'Submit', exact: true });

    await email.fill('visitor@example.org');
    await widget.getByText('Click to verify', { exact: true }).click();
    await expect(submit).toBeEnabled({ timeout: 60_000 });
    await email.fill(validation.value);
    await email.press('Tab');
    expect(await widget.evaluate((element) => element._privateCaptcha.solution())).toBeTruthy();
    await submit.click();

    await expect(form.locator('.forminator-error-message')).toHaveText(validation.message);
    await expect(form.locator('.forminator-error-message')).toBeVisible();
    await expect.poll(() => widget.evaluate((element) => element._privateCaptcha.solution())).toBeFalsy();
    await expect(widget.getByText('Click to verify', { exact: true })).toBeVisible();
    await expect(submit).toBeDisabled();

    await email.fill('visitor@example.org');
    await email.press('Tab');
    await widget.getByText('Click to verify', { exact: true }).click();
    await expect(submit).toBeEnabled({ timeout: 60_000 });
    expect(await submitForminator(page, submit)).toMatchObject({ success: true });
    await expect(form.locator('.forminator-response-message')).toContainText('Forminator e2e submission received.');
  });
}
