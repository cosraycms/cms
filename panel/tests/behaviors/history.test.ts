import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { install } from '../../src/behaviors/history';

interface HistoryContext {
	push?: unknown;
	replace?: unknown;
	request?: { method?: string };
	response?: { raw?: { redirected?: boolean } };
}

function respond(ctx: HistoryContext): HistoryContext {
	document.dispatchEvent(new CustomEvent('htmx:before:response', { detail: { ctx } }));

	return ctx;
}

function post(redirected = false): HistoryContext {
	return {
		push: null,
		replace: null,
		request: { method: 'POST' },
		response: { raw: { redirected } },
	};
}

describe('history behavior', () => {
	let uninstall: () => void;

	beforeEach(() => {
		uninstall = install();
	});

	afterEach(() => {
		uninstall();
	});

	it('keeps a post that answers in place out of the history', () => {
		expect(respond(post()).push).toBe('false');
	});

	it('lets a redirected post push the page it arrived at', () => {
		expect(respond(post(true)).push).toBeNull();
	});

	it('lets a get push its address', () => {
		const ctx = respond({
			push: null,
			replace: null,
			request: { method: 'GET' },
			response: { raw: {} },
		});

		expect(ctx.push).toBeNull();
	});

	it('leaves an explicit push or replace to the element', () => {
		expect(respond({ ...post(), push: 'true' }).push).toBe('true');
		expect(respond({ ...post(), replace: 'true' }).push).toBeNull();
	});

	it('stops once uninstalled', () => {
		uninstall();

		expect(respond(post()).push).toBeNull();
		uninstall = install();
	});
});
