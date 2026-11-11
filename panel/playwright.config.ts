import { defineConfig } from '@playwright/test';

const port = 2140;

export default defineConfig({
	testDir: 'e2e',
	// The tests share one database and its nodes.
	workers: 1,
	reporter: [['list']],
	use: {
		baseURL: `http://127.0.0.1:${port}`,
		locale: 'en-US',
		viewport: { width: 1440, height: 1000 },
		screenshot: 'only-on-failure',
		launchOptions: {
			// An installed browser saves `playwright install`.
			executablePath: process.env.COSRAY_E2E_CHROMIUM || undefined,
		},
	},
	webServer: {
		command: `php ../tests/Browser/setup.php && cd ../tests/Browser && php -S 127.0.0.1:${port} -t .run/public router.php`,
		url: `http://127.0.0.1:${port}/panel/login`,
		env: { PHP_CLI_SERVER_WORKERS: '4' },
		reuseExistingServer: false,
		timeout: 60_000,
	},
});
