import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { activate } from '@celema/verba';
import { install, toast } from '../../src/lib/toast';
import { catalog } from '../catalog';

beforeAll(() => activate(catalog()));

let uninstall: (() => void) | null = null;

beforeEach(() => {
	vi.useFakeTimers();
	document.body.innerHTML = '<div id="cms-toasts" class="cms-toasts" aria-live="polite"></div>';
	uninstall = install();
});

afterEach(() => {
	uninstall?.();
	uninstall = null;
	document.body.innerHTML = '';
	vi.useRealTimers();
});

function toasts(): HTMLElement[] {
	return Array.from(document.querySelectorAll<HTMLElement>('#cms-toasts .toast'));
}

describe('toasts', () => {
	it('hides a success after a while but keeps an error until closed', () => {
		toast('success', 'Uploaded');
		toast('error', 'Refused');

		expect(toasts().map((item) => item.className)).toEqual(['toast is-success', 'toast is-error']);
		expect(toasts()[1].getAttribute('role')).toBe('alert');

		vi.advanceTimersByTime(60000);
		expect(toasts().map((item) => item.textContent)).toEqual(['Refused×']);

		toasts()[0].querySelector<HTMLButtonElement>('button.close')!.click();
		expect(toasts()).toHaveLength(0);
	});

	it('keeps a toast while the pointer rests on it', () => {
		toast('info', 'Read me');
		const item = toasts()[0];

		item.dispatchEvent(new Event('pointerenter'));
		vi.advanceTimersByTime(60000);
		expect(toasts()).toHaveLength(1);

		item.dispatchEvent(new Event('pointerleave'));
		vi.advanceTimersByTime(5000);
		expect(toasts()).toHaveLength(0);
	});

	it('turns a toast a response appended into the region into a closable one', () => {
		document
			.getElementById('cms-toasts')!
			.insertAdjacentHTML('beforeend', '<div data-toast="error">Has children</div>');
		document.dispatchEvent(new Event('htmx:after:swap'));

		const [item] = toasts();
		expect(item.querySelector('.message')?.textContent).toBe('Has children');
		expect(item.querySelector('button.close')?.getAttribute('aria-label')).toBe('Close');
		expect(item.hasAttribute('data-toast')).toBe(false);
	});

	it('moves the toasts a full page rendered out of band into the region', () => {
		uninstall?.();
		document.body.insertAdjacentHTML(
			'afterbegin',
			'<div hx-swap-oob="beforeend:#cms-toasts" hidden><div data-toast="success">Deleted</div></div>',
		);
		uninstall = install();

		expect(toasts().map((item) => item.querySelector('.message')?.textContent)).toEqual([
			'Deleted',
		]);
		expect(document.querySelector('[hx-swap-oob]')).toBeNull();
	});
});
