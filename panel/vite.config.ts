import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { defineConfig, type Plugin } from 'vite';

// The optional dev server, for COSRAY_PANEL_DEV=1 on the PHP side. It only
// serves the panel stylesheet through dev.js, so style changes apply in
// place; PHP serves every script, the import map and the icons as in
// production, and a change to one of those, or to a view, reloads the page.
// Without this server the panel loads exactly as it does in production.

const root = fileURLToPath(new URL('.', import.meta.url));
const port = Number.parseInt(process.env.COSRAY_PANEL_DEV_PORT ?? '2001', 10);

function reload(): Plugin {
	return {
		name: 'cosray-reload',
		configureServer(server) {
			const changed = (file: string) => {
				if (/^(src|views|icons)\//.test(path.relative(root, file).split(path.sep).join('/'))) {
					server.ws.send({ type: 'full-reload' });
				}
			};

			server.watcher.on('change', changed);
			server.watcher.on('add', changed);
			server.watcher.on('unlink', changed);
		},
	};
}

export default defineConfig({
	plugins: [reload()],
	// Nothing to pre-bundle: dev.js imports stylesheets only.
	optimizeDeps: { noDiscovery: true, include: [] },
	server: {
		host: process.env.COSRAY_PANEL_DEV_HOST ?? 'localhost',
		port: Number.isFinite(port) ? port : 2001,
		strictPort: true,
		allowedHosts: true,
		cors: true,
	},
});
