import type { Component } from 'svelte';

import { writable, type Writable } from 'svelte/store';

export type ModalOptions = {
	hideClose?: boolean;
};

type ComponentContent = {
	kind: 'component';
	// eslint-disable-next-line @typescript-eslint/no-explicit-any
	component: Component<any>;
	props: object;
	options: ModalOptions;
};

type ElementContent = {
	kind: 'element';
	render: (host: HTMLElement) => (() => void) | void;
	options: ModalOptions;
};

export type ModalContent = ComponentContent | ElementContent;

export const modal: Writable<ModalContent | null> = writable(null);

// eslint-disable-next-line @typescript-eslint/no-explicit-any
export function open(component: Component<any>, props: object = {}, options: ModalOptions = {}) {
	modal.set({ kind: 'component', component, props, options });
}

/**
 * Modal content as plain DOM — the door for web components and the
 * window.Cosray bridge. The callback receives an empty host element
 * inside the modal chrome and may return a cleanup function.
 */
export function openElement(
	render: (host: HTMLElement) => (() => void) | void,
	options: ModalOptions = {},
): { close: () => void } {
	modal.set({ kind: 'element', render, options });

	return { close };
}

export function close() {
	modal.set(null);
}
