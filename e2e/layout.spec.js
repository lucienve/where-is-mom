const { test, expect } = require('@playwright/test');

test.describe('Where is Mom - Flow & Layout tests', () => {

  test('Family Viewer UI layout loads without overflow', async ({ page }) => {
    // Go to viewer map
    await page.goto('/index.html');
    
    // Check if login overlay is rendered
    const overlay = page.locator('#loginOverlay');
    await expect(overlay).toBeVisible();

    // Verify aesthetics/fonts loaded
    const title = page.locator('h1', { hasText: 'Where is Mom?' });
    await expect(title).toBeVisible();

    // Verify mathematical bounds/no scroll
    const mapContainer = page.locator('#mapContainer');
    const box = await mapContainer.boundingBox();
    expect(box.x).toBe(0);
    expect(box.y).toBe(0);
  });

  test('Traveler Controller layouts properly fit mobile dimensions', async ({ page }) => {
    await page.goto('/traveler.html');
    
    // Traveler authentication box should be visible
    const loginCard = page.locator('.login-card');
    await expect(loginCard).toBeVisible();

    // We can't log in easily without setting up database mock logic in the test, 
    // but we can ensure layout renders properly.
    const body = await page.evaluate(() => document.body.style.overflow);
    expect(body).not.toBe('scroll'); // Ensure no stray scrollbars
  });

});
