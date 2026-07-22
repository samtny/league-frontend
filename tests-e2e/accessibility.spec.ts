import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

const routes = ['/', '/standings', '/schedule', '/about', '/venues', '/roster', '/rules'];

for (const route of routes) {
    test(`${route} has no automatically detectable accessibility violations`, async ({ page }) => {
        await page.goto(route);

        const results = await new AxeBuilder({ page })
            .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'])
            .analyze();

        expect(results.violations, JSON.stringify(results.violations, null, 2)).toEqual([]);
    });
}
