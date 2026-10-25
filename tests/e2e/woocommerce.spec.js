import { expect, test } from '@playwright/test';

async function addProductToCart(page) {
  await page.goto('/?product=woocommerce-e2e-product');
  await expect(page.getByRole('heading', { name: 'E2E virtual product', exact: true })).toBeVisible();
  await page.getByRole('button', { name: 'Add to cart', exact: true }).click();
  await expect(page.getByRole('alert')).toContainText('“E2E virtual product” has been added to your cart.');
}

async function expectOrderConfirmation(page) {
  await expect(page).toHaveURL(/order-received/);
  await expect(page.getByText('Thank you. Your order has been received.', { exact: true })).toBeVisible();
  await expect(page.getByText('Test order: no payment is collected.', { exact: true })).toBeVisible();
  await expect(page.locator('main')).toContainText('Direct bank transfer');
  await expect(page.locator('main')).toContainText('E2E virtual product');
}

async function prepareBlockCheckout(page) {
  await addProductToCart(page);
  await page.goto('/?pagename=checkout');

  const form = page.getByRole('form', { name: 'Checkout', exact: true });
  await expect(page.locator('.wp-block-woocommerce-checkout')).toBeVisible();
  await form.getByLabel('Email address', { exact: true }).fill('visitor@example.org');
  await form.getByLabel('First name', { exact: true }).fill('Test');
  await form.getByLabel('Last name', { exact: true }).fill('Customer');
  await form.getByLabel('Address', { exact: true }).fill('123 Example Street');
  await form.getByLabel('City', { exact: true }).fill('Los Angeles');
  await form.getByLabel('ZIP Code', { exact: true }).fill('90001');
  await form.getByLabel('ZIP Code', { exact: true }).press('Tab');
  await expect(form.getByRole('radio', { name: 'Direct bank transfer', exact: true })).toBeChecked();
  return form;
}

test('WooCommerce block checkout rejects an order with unsolved Private Captcha even if Place Order is re-enabled', async ({ page }) => {
  const form = await prepareBlockCheckout(page);
  const widget = form.locator('.private-captcha');
  const submit = form.getByRole('button', { name: 'Place Order', exact: true });

  await expect(widget.getByText('Click to verify', { exact: true })).toBeVisible();
  await expect(submit).toBeDisabled();
  expect(await widget.evaluate((element) => element._privateCaptcha.solution())).toBeFalsy();

  // Bypass only the button guard; submit through WooCommerce's real checkout handler.
  await submit.evaluate((button) => { button.disabled = false; });
  await expect(submit).toBeEnabled();
  const responsePromise = page.waitForResponse((response) =>
    response.request().method() === 'POST' &&
    /\/wc\/store\/v1\/checkout(?:[?&]|$)/.test(decodeURIComponent(response.url())),
  );
  await submit.click();
  const response = await responsePromise;
  const submitted = response.request().postDataJSON();

  expect(submitted.payment_method).toBe('bacs');
  expect(submitted.extensions?.['private-captcha']?.solution).toBeFalsy();
  expect(response.ok()).toBe(false);
  expect(await response.json()).toMatchObject({
    code: 'private_captcha_failed',
    message: 'Captcha verification failed. Please try again.',
  });
  await expect(page.locator('main').getByText('Captcha verification failed. Please try again.', { exact: true })).toBeVisible();
  await expect(page).toHaveURL('/?pagename=checkout');
  await expect(page.getByText('Thank you. Your order has been received.', { exact: true })).toHaveCount(0);
});

test('WooCommerce block checkout accepts a guest order with Private Captcha and bank transfer', async ({ page }) => {
  const form = await prepareBlockCheckout(page);

  const widget = form.locator('.private-captcha');
  await expect(widget).toHaveCount(1);
  await widget.getByText('Click to verify', { exact: true }).click();
  await expect.poll(() => widget.evaluate((element) => !!element._privateCaptcha.solution()), { timeout: 60_000 }).toBe(true);
  const solution = await widget.evaluate((element) => element._privateCaptcha.solution());

  // Capture the real result before WooCommerce redirects and Chrome discards the body.
  let order;
  await page.route((url) => /\/wc\/store\/v1\/checkout(?:[?&]|$)/.test(decodeURIComponent(url.href)), async (route) => {
    if (route.request().method() !== 'POST') {
      await route.continue();
      return;
    }
    const upstream = await route.fetch();
    order = await upstream.json();
    await route.fulfill({ response: upstream });
  });
  const responsePromise = page.waitForResponse((response) =>
    response.request().method() === 'POST' &&
    /\/wc\/store\/v1\/checkout(?:[?&]|$)/.test(decodeURIComponent(response.url())),
  );
  await form.getByRole('button', { name: 'Place Order', exact: true }).click();
  const response = await responsePromise;

  expect(response.request().postDataJSON()).toMatchObject({
    payment_method: 'bacs',
    extensions: { 'private-captcha': { solution } },
  });
  expect(response.ok()).toBe(true);
  expect(order).toMatchObject({ status: 'on-hold', payment_method: 'bacs', payment_result: { payment_status: 'success' } });
  expect(order.order_id).toBeGreaterThan(0);
  await expectOrderConfirmation(page);
});

async function prepareLegacyCheckout(page) {
  await addProductToCart(page);
  await page.goto('/?pagename=woocommerce-legacy-checkout-e2e');

  const form = page.locator('form.checkout');
  await expect(form).toBeVisible();
  await form.getByLabel('First name', { exact: false }).fill('Test');
  await form.getByLabel('Last name', { exact: false }).fill('Customer');
  await form.getByLabel('Street address', { exact: false }).fill('123 Example Street');
  await form.getByLabel('Town / City', { exact: false }).fill('Los Angeles');
  await form.getByLabel('Email address', { exact: false }).fill('visitor@example.org');

  // Address updates replace the payment fragment; wait before using its CAPTCHA widget.
  const updatePromise = page.waitForResponse((response) =>
    new URL(response.url()).searchParams.get('wc-ajax') === 'update_order_review' &&
    response.request().postData()?.includes('90001'),
  );
  // The legacy address updater listens for keydown, not just input/change.
  await form.getByLabel('ZIP Code', { exact: false }).pressSequentially('90001');
  await form.getByLabel('ZIP Code', { exact: false }).press('Tab');
  await updatePromise;
  await expect(form.locator('.blockUI')).toHaveCount(0);
  await expect(form.locator('input[name="payment_method"][value="bacs"]')).toBeChecked();
  return form;
}

test('WooCommerce legacy checkout rejects an order with unsolved Private Captcha even if Place order is re-enabled', async ({ page }) => {
  const form = await prepareLegacyCheckout(page);
  const widget = form.locator('.private-captcha');
  const submit = form.getByRole('button', { name: 'Place order', exact: true });

  await expect(widget.getByText('Click to verify', { exact: true })).toBeVisible();
  await expect(submit).toBeDisabled();
  expect(await widget.evaluate((element) => element._privateCaptcha.solution())).toBeFalsy();

  // Bypass only the button guard; submit through WooCommerce's real checkout handler.
  await submit.evaluate((button) => { button.disabled = false; });
  await expect(submit).toBeEnabled();
  const responsePromise = page.waitForResponse((response) =>
    response.request().method() === 'POST' &&
    new URL(response.url()).searchParams.get('wc-ajax') === 'checkout',
  );
  await submit.click();
  const response = await responsePromise;
  const submitted = new URLSearchParams(response.request().postData());

  expect(submitted.get('payment_method')).toBe('bacs');
  expect(submitted.get('wp-private-captcha-solution')).toBeFalsy();
  expect(response.ok()).toBe(true);
  expect(await response.json()).toMatchObject({
    result: 'failure',
    messages: expect.stringContaining('Captcha verification failed. Please try again.'),
  });
  await expect(form.getByRole('alert')).toHaveText('Captcha verification failed. Please try again.');
  await expect(form.getByRole('alert')).toBeVisible();
  await expect(page).toHaveURL('/?pagename=woocommerce-legacy-checkout-e2e');
  await expect(page.getByText('Thank you. Your order has been received.', { exact: true })).toHaveCount(0);
});

test('WooCommerce legacy checkout accepts a guest order with Private Captcha and bank transfer', async ({ page }) => {
  const form = await prepareLegacyCheckout(page);

  const widget = form.locator('.private-captcha');
  const submit = form.getByRole('button', { name: 'Place order', exact: true });
  await expect(widget).toHaveCount(1);
  await expect(submit).toBeDisabled();
  await widget.getByText('Click to verify', { exact: true }).click();
  await expect(submit).toBeEnabled({ timeout: 60_000 });
  const solution = await widget.evaluate((element) => element._privateCaptcha.solution());
  expect(solution).toBeTruthy();

  let result;
  await page.route((url) => url.searchParams.get('wc-ajax') === 'checkout', async (route) => {
    const upstream = await route.fetch();
    result = await upstream.json();
    await route.fulfill({ response: upstream });
  });
  const responsePromise = page.waitForResponse((response) =>
    response.request().method() === 'POST' &&
    new URL(response.url()).searchParams.get('wc-ajax') === 'checkout',
  );
  await submit.click();
  const response = await responsePromise;
  const submitted = new URLSearchParams(response.request().postData());

  expect(submitted.get('payment_method')).toBe('bacs');
  expect(submitted.get('wp-private-captcha-solution')).toBe(solution);
  expect(response.ok()).toBe(true);
  expect(result).toMatchObject({ result: 'success' });
  await expectOrderConfirmation(page);
});
