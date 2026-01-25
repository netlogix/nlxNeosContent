// @ts-check
const { test, expect } = require('@playwright/test');

test('homepage loads successfully', async ({ page }) => {
  const response = await page.goto('http://localhost:80');
  expect(response.status()).toBe(200);
});
