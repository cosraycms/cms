import type { BridgeSystem, CosrayBridge, ModalOptions, UploadResult } from '$lib/bridge';

import { __ } from '$lib/locale';
import { icon } from '$lib/icons';
import { openDialog } from '$lib/dialogs';

/**
 * Installs window.Cosray without the editor island: the system payload
 * comes SSR-embedded from the page, modal chrome and toasts are plain
 * DOM. The bridge API (version 1) is unchanged for element controls.
 */
export function installBridge(system: BridgeSystem): void {
	if (window.Cosray?.version === 1) {
		return;
	}

	const bridge: CosrayBridge = {
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
					},
				});

				return (await response.json()) as UploadResult;
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

function openModal(
	render: (host: HTMLElement) => (() => void) | void,
	options: ModalOptions = {},
): { close(): void } {
	const active = document.activeElement;
	const opener = active instanceof HTMLElement && active !== document.body ? active : null;
	const dialog = document.createElement('dialog');
	dialog.className = 'cms-modal';
	if (options.label !== undefined) dialog.setAttribute('aria-label', options.label);
	if (options.size) dialog.dataset.size = options.size;

	if (!options.hideClose) {
		const button = document.createElement('button');
		button.type = 'button';
		button.className = 'modal-close';
		button.dataset.dialogClose = '';
		button.setAttribute('aria-label', __('common:close'));
		button.innerHTML = icon('x-lg');
		dialog.append(button);
	}

	const host = document.createElement('div');
	host.className = 'element';
	dialog.append(host);
	document.body.append(dialog);
	let cleanup: (() => void) | void;

	try {
		cleanup = render(host);
	} catch (error) {
		dialog.remove();
		throw error;
	}

	return openDialog(dialog, {
		opener,
		owner: options.owner ?? opener ?? document.getElementById('cosray-system-data') ?? undefined,
		onClose: () => {
			try {
				cleanup?.();
			} finally {
				dialog.remove();
			}
		},
	});
}

const TIMEOUTS = { success: 3000, error: 30000 };

function toast(kind: 'success' | 'error', message: string): void {
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
