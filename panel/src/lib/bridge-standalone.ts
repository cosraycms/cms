import type { BridgeSystem, CosrayBridge, ModalOptions, UploadResult } from '$lib/bridge';

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

		async upload(type, node, file) {
			const body = new FormData();
			body.append('file', file);

			try {
				const response = await fetch(`${system.prefix}/media/${type}/node/${node}`, {
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
				return { ok: false, error: 'Upload failed' };
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
	const overlay = document.createElement('div');
	overlay.className = 'modal cms-modal-overlay';
	const container = document.createElement('div');
	container.className = 'modal-container cms-modal-container';
	overlay.append(container);

	let cleanup: (() => void) | void = undefined;
	const close = () => {
		cleanup?.();
		overlay.remove();
	};

	if (!options.hideClose) {
		const button = document.createElement('button');
		button.type = 'button';
		button.className = 'cms-modal-close';
		button.setAttribute('aria-label', 'close');
		button.textContent = '×';
		button.addEventListener('click', close);
		container.append(button);
	}

	const host = document.createElement('div');
	host.className = 'cms-modal-element';
	container.append(host);
	document.body.append(overlay);
	cleanup = render(host);

	return { close };
}

const TIMEOUTS = { success: 3000, error: 30000 };

function toast(kind: 'success' | 'error', message: string): void {
	let stack = document.querySelector('.toasts.pos-bottom');

	if (!stack) {
		stack = document.createElement('div');
		stack.className = 'toasts pos-bottom';
		document.body.append(stack);
	}

	const item = document.createElement('button');
	item.type = 'button';
	item.className = `toast toast-${kind}`;
	const content = document.createElement('div');
	content.className = 'cms-toast-content';
	content.textContent = message;
	item.append(content);
	item.addEventListener('click', () => item.remove());
	stack.prepend(item);
	setTimeout(() => item.remove(), TIMEOUTS[kind]);
}
