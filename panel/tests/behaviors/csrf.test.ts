import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { install } from '../../src/behaviors/csrf';

interface Request {
	method?: string;
	headers?: Record<string, string>;
}

function configure(request: Request): Request {
	document.dispatchEvent(new CustomEvent('htmx:config:request', { detail: { ctx: { request } } }));

	return request;
}

describe('csrf behavior', () => {
	let uninstall: () => void;

	beforeEach(() => {
		document.head.innerHTML = '<meta name="csrf-token" content="token-a">';
		uninstall = install();
	});

	afterEach(() => {
		uninstall();
		document.head.innerHTML = '';
	});

	it('sends the token with unsafe requests only', () => {
		const post = configure({ method: 'POST', headers: { 'HX-Request': 'true' } });
		const remove = configure({ method: 'delete', headers: {} });
		const get = configure({ method: 'GET', headers: { 'HX-Request': 'true' } });

		expect(post.headers).toEqual({ 'HX-Request': 'true', 'X-CSRF-Token': 'token-a' });
		expect(remove.headers).toEqual({ 'X-CSRF-Token': 'token-a' });
		expect(get.headers).toEqual({ 'HX-Request': 'true' });
	});
});
