import { writable } from 'svelte/store';

const { subscribe, update, set } = writable([] as Toast[]);

export interface Toast {
	kind: 'success' | 'error' | 'warning';
	title?: string;
	message?: string;
}

export function remove(toast: Toast) {
	update((toasts) =>
		toasts.filter((item) => {
			return item !== toast;
		}),
	);
}

export function add(toast: Toast, timeout?: number) {
	update((toasts) => [toast, ...toasts]);

	if (toast.kind === 'error') {
		setTimeout(() => {
			remove(toast);
		}, timeout || 30000);
	} else {
		setTimeout(() => {
			remove(toast);
		}, timeout || 3000);
	}
}

export function reset() {
	set([] as Toast[]);
}

export default { subscribe, add, remove, reset };
