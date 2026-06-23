import { writable, derived, type Writable } from 'svelte/store';
import toast from '$lib/toast';
import type { Toast } from '$lib/toast';
import type { Node } from '$types/data';
import type { Field } from '$types/fields';

const pristine = writable(true);
const dirty = derived(pristine, ($pristine) => !$pristine);
const currentNode: Writable<null | Node> = writable(null);
const currentFields: Writable<null | Field[]> = writable(null);

function inIframe(): boolean {
	try {
		return window.self !== window.top;
	} catch (_) {
		return true;
	}
}

function setDirty() {
	pristine.set(false);

	if (window.top && inIframe()) {
		window.top.postMessage('cms-dirty', '*');
	}
}

function setPristine() {
	pristine.set(true);

	if (window.top && inIframe()) {
		window.top.postMessage('cms-pristine', '*');
	}
}

type ToastInput = string | Omit<Toast, 'kind'>;

function toToast(message: ToastInput, kind: Toast['kind']): Toast {
	return typeof message === 'string' ? { kind, message } : { ...message, kind };
}

function success(message: ToastInput) {
	setPristine();
	toast.reset();

	if (message) {
		toast.add(toToast(message, 'success'));
	}
}

function error(message: ToastInput) {
	toast.add(toToast(message, 'error'));
}

function broadcastOk() {
	if (window.top && inIframe()) {
		window.top.postMessage('cms-ok', '*');
	}
}

function broadcastCancel() {
	if (window.top && inIframe()) {
		window.top.postMessage('cms-cancel', '*');
	}
}

export {
	pristine,
	dirty,
	setDirty,
	setPristine,
	success,
	error,
	currentNode,
	currentFields,
	broadcastOk,
	broadcastCancel,
};
