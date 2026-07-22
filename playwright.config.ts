import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
    testDir: './tests-e2e',
    fullyParallel: true,
    reporter: 'list',
    use: {
        baseURL: 'http://slope.pinballleague.org:8000',
    },
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],
    webServer: {
        command: 'php artisan serve --port=8000',
        url: 'http://slope.pinballleague.org:8000',
        reuseExistingServer: !process.env.CI,
    },
});
