import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './tests/e2e',
  workers: 1,
  timeout: 120_000,
  expect: { timeout: 10_000 },
  use: {
    browserName: 'chromium',
    baseURL: 'http://localhost:18081',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
});
