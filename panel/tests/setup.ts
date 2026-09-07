import { afterEach, beforeEach, vi } from 'vitest';

// jsdom has no top layer or layout. Browser acceptance is still required.
Range.prototype.getBoundingClientRect = () => new DOMRect();
Range.prototype.getClientRects = () => Object.assign([], { item: () => new DOMRect() });
HTMLDialogElement.prototype.showModal = function () {
	if (!this.isConnected) throw new DOMException('Dialog is disconnected', 'InvalidStateError');
	this.open = true;
	this.focus();
};
HTMLDialogElement.prototype.close = function () {
	if (!this.open) return;
	this.open = false;
	queueMicrotask(() => this.dispatchEvent(new Event('close')));
};
HTMLElement.prototype.checkVisibility = function () {
	if (this.closest('[hidden], [inert], dialog:not([open])')) return false;
	for (let element: HTMLElement | null = this; element; element = element.parentElement) {
		const style = getComputedStyle(element);
		if (style.display === 'none' || style.visibility === 'hidden') return false;
	}
	return true;
};

function message(args: unknown[]): string {
	return args
		.map((value) => {
			if (value instanceof Error) {
				return value.stack ?? value.message;
			}

			return typeof value === 'string' ? value : String(value);
		})
		.join(' ');
}

beforeEach(() => {
	vi.spyOn(console, 'error').mockImplementation((...args: unknown[]) => {
		throw new Error(`Unexpected console.error: ${message(args)}`);
	});
});

afterEach(() => {
	vi.restoreAllMocks();
});
