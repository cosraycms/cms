/** @import { BridgeSystem, CosrayBridge, UploadResult } from '../types/bridge' */

import { __ } from './locale.js';
import { csrfToken, HEADER, noticeRefusal } from './csrf.js';
import { openModal } from './modal.js';

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

const TIMEOUTS = { success: 3000, error: 30000 };

/**
 * @param {'success' | 'error'} kind
 * @param {string} message
 */
function toast(kind, message) {
	let stack = document.querySelector('.cms-toasts');

	if (!stack) {
		stack = document.createElement('div');
		stack.className = 'cms-toasts';
		document.body.append(stack);
	}

	const item = document.createElement('button');
	item.type = 'button';
	item.className = `toast is-${kind}`;
	const content = document.createElement('div');
	content.className = 'cms-toast-content';
	content.textContent = message;
	item.append(content);
	item.addEventListener('click', () => item.remove());
	stack.prepend(item);
	setTimeout(() => item.remove(), TIMEOUTS[kind]);
}
