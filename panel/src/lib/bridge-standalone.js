/** @import { BridgeSystem, CosrayBridge, UploadResult } from '../types/bridge' */

import { __ } from './locale.js';
import { csrfToken, HEADER, noticeRefusal } from './csrf.js';
import { openModal } from './modal.js';
import { toast } from './toast.js';

/**
 * Installs window.Cosray without the editor island: the system payload
 * comes SSR-embedded from the page, modal chrome and toasts are plain
 * DOM. The bridge API (version 1) is unchanged for element controls.
 *
 * @param {BridgeSystem} system
 */
export function installBridge(system) {
	if (window.Cosray?.version === 1) {
		return;
	}

	/** @type {CosrayBridge} */
	const bridge = {
		version: 1,

		system() {
			return system;
		},

		async upload(type, file) {
			const body = new FormData();
			body.append('file', file);

			try {
				const response = await fetch(`${system.prefix}/media/${type}`, {
					method: 'POST',
					body,
					credentials: 'same-origin',
					headers: {
						'X-Requested-With': 'xmlhttprequest',
						Accept: 'application/json',
						[HEADER]: csrfToken(),
					},
				});
				noticeRefusal(response);

				return /** @type {UploadResult} */ (await response.json());
			} catch {
				return { ok: false, error: __('upload:failed') };
			}
		},

		modal: {
			open: openModal,
		},

		toast: {
			success(message) {
				toast('success', message);
			},
			error(message) {
				toast('error', message);
			},
		},
	};

	window.Cosray = bridge;
}
