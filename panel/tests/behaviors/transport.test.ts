import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { install } from '../../src/behaviors/transport';

interface TransportRequest {
	form?: unknown;
	body?: unknown;
	headers?: Record<string, string>;
}

function fire(request: TransportRequest): TransportRequest {
	document.dispatchEvent(new CustomEvent('htmx:before:request', { detail: { ctx: { request } } }));

	return request;
}

describe('transport behavior', () => {
	let uninstall: () => void;

	beforeEach(() => {
		uninstall = install();
	});

	afterEach(() => {
		uninstall();
		document.body.innerHTML = '';
	});

	function jsonForm(): HTMLFormElement {
		const form = document.createElement('form');
		form.setAttribute('data-json-form', '');
		document.body.append(form);

		return form;
	}

	it('re-encodes a marked form body as nested JSON', () => {
		const body = new URLSearchParams();
		body.append('content[title][value][zxx]', 'Hello');
		body.append('_complete', '1');

		const request = fire({ form: jsonForm(), body, headers: { 'HX-Request': 'true' } });

		expect(JSON.parse(request.body as string)).toEqual({
			content: { title: { value: { zxx: 'Hello' } } },
			_complete: '1',
		});
		expect(request.headers).toEqual({
			'HX-Request': 'true',
			'Content-Type': 'application/json',
		});
	});

	it('ignores forms without the marker', () => {
		const form = document.createElement('form');
		document.body.append(form);
		const body = new URLSearchParams([['a', '1']]);

		const request = fire({ form, body, headers: {} });

		expect(request.body).toBe(body);
		expect(request.headers).toEqual({});
	});

	it('ignores requests without a URLSearchParams body', () => {
		const request = fire({ form: jsonForm(), body: null, headers: {} });

		expect(request.body).toBeNull();
		expect(request.headers).toEqual({});
	});

	it('stops encoding once uninstalled', () => {
		uninstall();
		const body = new URLSearchParams([['a', '1']]);

		const request = fire({ form: jsonForm(), body, headers: {} });

		expect(request.body).toBe(body);
		uninstall = install();
	});
});
