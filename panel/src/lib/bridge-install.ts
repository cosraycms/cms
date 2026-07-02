import { get } from 'svelte/store';

import { type CosrayBridge, type UploadResult } from '$lib/bridge';
import { openElement } from '$lib/modal';
import req from '$lib/req';
import { system } from '$lib/sys';
import toast from '$lib/toast';

export function installBridge(): () => void {
	if (window.Cosray?.version === 1) {
		return () => {};
	}

	const bridge: CosrayBridge = {
		version: 1,

		system() {
			const sys = get(system);

			return {
				locale: sys.locale,
				defaultLocale: sys.defaultLocale,
				locales: sys.locales.map(({ id, title }) => ({ id, title })),
				customLocales: sys.customLocales,
				prefix: sys.prefix,
				assets: sys.assets,
				debug: sys.debug,
				allowedFiles: sys.allowedFiles,
			};
		},

		async upload(type, node, file) {
			const formData = new FormData();
			formData.append('file', file);

			const response = await req.post(`${get(system).prefix}/media/${type}/node/${node}`, formData);

			return (response?.data ?? { ok: false, error: 'Upload failed' }) as UploadResult;
		},

		modal: {
			open(render, options = {}) {
				return openElement(render, options);
			},
		},

		toast: {
			success(message) {
				toast.add({ kind: 'success', message });
			},
			error(message) {
				toast.add({ kind: 'error', message });
			},
		},
	};

	window.Cosray = bridge;

	return () => {
		if (window.Cosray === bridge) {
			delete window.Cosray;
		}
	};
}
