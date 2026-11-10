import { afterEach, beforeAll, beforeEach, describe, expect, it } from 'vitest';
import { activate } from '@celema/verba';
import { EVENT } from '../../src/behaviors/autosave';
import { install } from '../../src/behaviors/status';
import { install as installToasts } from '../../src/lib/toast';
import { catalog } from '../catalog';

beforeAll(() => activate(catalog()));

let uninstall: Array<() => void> = [];

function head(mine: string, draft = false): string {
	return `
		<button id="editor-status-dots" data-draft="${draft}" data-mine="${mine}">
			<span class="dot" data-stage="website" data-state="online"></span>
			<span class="dot" data-stage="mine" data-state="${mine}"></span>
			<span class="tooltip">
				<span class="row" data-stage="mine"><strong>Mine</strong> · <span class="text"></span></span>
			</span>
		</button>
		<button id="save" data-needs="mine"><span data-save-label data-normal="Save" data-conflict="Save anyway">Save</span></button>
		<button id="publish" data-needs="mine draft">Publish</button>`;
}

function autosave(state: string): void {
	document.dispatchEvent(new CustomEvent(EVENT, { detail: { state } }));
}

function mine(): string | undefined {
	return document.querySelector<HTMLElement>('#editor-status-dots > [data-stage="mine"]')?.dataset
		.state;
}

function disabled(id: string): boolean {
	return document.querySelector<HTMLButtonElement>(`#${id}`)!.disabled;
}

beforeEach(() => {
	document.body.innerHTML = '<div id="cms-toasts"></div>';
});

afterEach(() => {
	uninstall.forEach((off) => off());
	uninstall = [];
	document.body.innerHTML = '';
});

describe('status', () => {
	it('moves the user’s stage with the autosave and enables saving', () => {
		document.body.insertAdjacentHTML('beforeend', head('none'));
		uninstall.push(install());

		expect(disabled('save')).toBe(true);
		expect(disabled('publish')).toBe(true);

		autosave('pending');
		expect(mine()).toBe('saving');
		expect(disabled('save')).toBe(false);
		expect(disabled('publish')).toBe(false);

		autosave('kept');
		expect(mine()).toBe('present');

		autosave('failed');
		expect(mine()).toBe('error');

		autosave('dropped');
		expect(mine()).toBe('none');
		expect(disabled('save')).toBe(true);
	});

	it('keeps a conflict and offers to save anyway', () => {
		document.body.insertAdjacentHTML('beforeend', head('conflict', true));
		uninstall.push(install());

		expect(document.querySelector('#save')?.textContent).toBe('Save anyway');
		autosave('kept');
		expect(mine()).toBe('conflict');
		expect(disabled('publish')).toBe(false);
	});

	it('lets a draft be published without edits', () => {
		document.body.insertAdjacentHTML('beforeend', head('none', true));
		uninstall.push(install());

		expect(disabled('save')).toBe(true);
		expect(disabled('publish')).toBe(false);
	});

	it('says a form succeeded once, for its own request', () => {
		document.body.insertAdjacentHTML(
			'beforeend',
			'<form id="discard" data-done-toast="Discarded"></form>',
		);
		uninstall.push(install(), installToasts());
		const form = document.getElementById('discard')!;
		const request = (method: string, status: number) =>
			form.dispatchEvent(
				new CustomEvent('htmx:after:request', {
					bubbles: true,
					detail: { ctx: { sourceElement: form, request: { method }, response: { status } } },
				}),
			);

		request('POST', 200);
		request('GET', 200);
		request('POST', 409);

		expect(
			Array.from(document.querySelectorAll('#cms-toasts .toast .message'), (t) => t.textContent),
		).toEqual(['Discarded']);
	});
});
