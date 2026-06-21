import assert from 'node:assert/strict';
import { beforeEach, describe, it } from 'node:test';

import { JSDOM } from 'jsdom';

import { findValueInput, syncValueInput } from './input';

describe('value input helpers', () => {
	beforeEach(() => {
		const dom = new JSDOM('<!doctype html><html><body></body></html>');
		globalThis.document = dom.window.document;
		globalThis.Event = dom.window.Event;
		globalThis.CustomEvent = dom.window.CustomEvent;
		globalThis.HTMLElement = dom.window.HTMLElement;
		globalThis.HTMLInputElement = dom.window.HTMLInputElement;
		globalThis.HTMLTextAreaElement = dom.window.HTMLTextAreaElement;
	});

	it('finds a textarea by data-value-input', () => {
		document.body.innerHTML =
			'<textarea id="code"></textarea><cosray-code-editor data-value-input="code"></cosray-code-editor>';
		const host = document.querySelector('cosray-code-editor');

		assert.ok(host instanceof HTMLElement);
		assert.equal(findValueInput(host), document.getElementById('code'));
	});

	it('syncs the native input and dispatches a component change event', () => {
		document.body.innerHTML =
			'<textarea id="code"></textarea><cosray-code-editor></cosray-code-editor>';
		const input = document.getElementById('code') as HTMLTextAreaElement;
		const host = document.querySelector('cosray-code-editor') as HTMLElement;
		let inputEvents = 0;
		let changeEvents = 0;
		let changeDetail: unknown;
		input.addEventListener('input', () => {
			inputEvents++;
		});
		host.addEventListener('cosray:change', event => {
			changeEvents++;
			changeDetail = (event as CustomEvent<{ value: string }>).detail;
		});

		syncValueInput(host, input, 'echo 13;');

		assert.equal(input.value, 'echo 13;');
		assert.equal(inputEvents, 1);
		assert.equal(changeEvents, 1);
		assert.deepEqual(changeDetail, { value: 'echo 13;' });
	});
});
