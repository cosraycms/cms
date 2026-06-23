/// <reference types="vitest/config" />
import path from 'path';
import { sveltekit } from '@sveltejs/kit/vite';
import { defineConfig } from 'vite';

const oldPanelPath = process.env.COSRAY_PANEL_PATH ? process.env.COSRAY_PANEL_PATH : 'cms';
const devport = process.env.COSRAY_DEV_PORT ? parseInt(process.env.COSRAY_DEV_PORT, 10) : 2009;
const devhost = process.env.COSRAY_DEV_HOST ? process.env.COSRAY_DEV_HOST : 'localhost';
const appport = process.env.COSRAY_APP_PORT ? parseInt(process.env.COSRAY_APP_PORT, 10) : 1983;
const apphost = process.env.COSRAY_APP_HOST ? process.env.COSRAY_APP_HOST : 'localhost';
const target = {
	target: `http://${apphost}:${appport}`,
	secure: false,
};

export default defineConfig({
	plugins: [sveltekit()],
	server: {
		port: devport,
		host: devhost,
		strictPort: true,
		allowedHosts: true, // TODO: Check if this is necessary. Currently active to allow working with OrbStack domains
		proxy: {
			[`/${oldPanelPath}/api`]: target,
			[`/${oldPanelPath}/boot`]: target,
			'/assets': target,
			'/cache': target,
			'/media': target,
			'/images': target,
			'/preview': target,
			'/vendor': target,
			'/fonts': target,
		},
	},
	resolve: {
		alias: {
			$lib: path.resolve('./src/lib'),
			$types: path.resolve('./src/types'),
			$shell: path.resolve('./src/shell'),
		},
	},
	test: {
		environment: 'jsdom',
		include: ['tests/**/*.test.ts'],
	},
});
