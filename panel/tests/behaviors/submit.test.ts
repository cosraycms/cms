import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { install } from '../../src/behaviors/submit';

let uninstall: (() => void) | null = null;

beforeEach(() => {
	uninstall = install();
});

afterEach(() => {
	uninstall?.();
	uninstall = null;
	document.body.innerHTML = '';
});

function setup(formId = 'node-editor-form'): HTMLFormElement {
	document.body.innerHTML = `<form id="${formId}">
		<button id="stray">Looks harmless</button>
		<button id="save" data-editor-submit>Save</button>
	</form>`;

	const form = document.querySelector('form');

	if (!form) {
		throw new Error('form missing');
	}

	return form;
}

function submit(form: HTMLFormElement, submitter: HTMLElement | null): SubmitEvent {
	const event = new SubmitEvent('submit', { bubbles: true, cancelable: true, submitter });
	form.dispatchEvent(event);

	return event;
}

describe('submit guard', () => {
	it('blocks a stray button defaulting to type=submit', () => {
		const form = setup();
		const event = submit(form, document.getElementById('stray'));

		expect(event.defaultPrevented).toBe(true);
	});

	it('blocks a submit without a submitter (Enter-to-save)', () => {
		const form = setup();
		const event = submit(form, null);

		expect(event.defaultPrevented).toBe(true);
	});

	it('lets a data-editor-submit action through', () => {
		const form = setup();
		const event = submit(form, document.getElementById('save'));

		expect(event.defaultPrevented).toBe(false);
	});

	it('leaves other forms alone', () => {
		const form = setup('some-other-form');
		const event = submit(form, document.getElementById('stray'));

		expect(event.defaultPrevented).toBe(false);
	});

	it('stops a blocked submit before bubble-phase handlers (htmx boost)', () => {
		const form = setup();
		let seen = 0;
		const boost = (): void => {
			seen += 1;
		};

		document.addEventListener('submit', boost);
		submit(form, document.getElementById('stray'));
		submit(form, document.getElementById('save'));
		document.removeEventListener('submit', boost);

		expect(seen).toBe(1);
	});
});
