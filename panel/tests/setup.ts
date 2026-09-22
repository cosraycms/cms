import { afterEach, beforeEach, vi } from 'vitest';

// jsdom has no top layer or layout. Browser acceptance is still required.
const popovers = new WeakSet<Element>();
const matches = Element.prototype.matches;
Element.prototype.matches = function (selector) {
	return selector === ':popover-open'
		? this.isConnected && popovers.has(this)
		: matches.call(this, selector);
};

function togglePopover(element: HTMLElement, open: boolean): void {
	if (open === popovers.has(element)) return;
	const event = new Event('beforetoggle', { cancelable: open });
	Object.defineProperties(event, {
		newState: { value: open ? 'open' : 'closed' },
		oldState: { value: open ? 'closed' : 'open' },
	});
	if (!element.dispatchEvent(event)) return;
	if (open) popovers.add(element);
	else popovers.delete(element);
	queueMicrotask(() => {
		const toggle = new Event('toggle');
		Object.defineProperties(toggle, {
			newState: { value: open ? 'open' : 'closed' },
			oldState: { value: open ? 'closed' : 'open' },
		});
		element.dispatchEvent(toggle);
	});
}
HTMLElement.prototype.showPopover = function () {
	togglePopover(this, true);
};
HTMLElement.prototype.hidePopover = function () {
	togglePopover(this, false);
};

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
// No viewport either: every media query fails unless a test stubs matchMedia.
window.matchMedia = (query) =>
	({
		matches: false,
		media: query,
		onchange: null,
		addEventListener() {},
		removeEventListener() {},
		addListener() {},
		removeListener() {},
		dispatchEvent: () => false,
	}) as MediaQueryList;
HTMLElement.prototype.checkVisibility = function () {
	if (this.closest('[hidden], [inert], dialog:not([open])')) return false;
	const popover = this.closest('[popover]');
	if (popover && !popovers.has(popover)) return false;
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
