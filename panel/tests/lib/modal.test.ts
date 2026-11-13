import { afterEach, describe, expect, it, vi } from 'vitest';

vi.mock('../../src/lib/locale.js', () => ({ __: (id: string) => id }));

import { alert, confirm, question } from '../../src/lib/modal';

afterEach(() => {
	document.body.replaceChildren();
});

function open(): HTMLDialogElement {
	return document.querySelector<HTMLDialogElement>('dialog.cms-modal[open]')!;
}

function footer(variant: string): HTMLButtonElement {
	return open().querySelector<HTMLButtonElement>(`.cms-modal-footer .cms-button-${variant}`)!;
}

describe('confirm', () => {
	it('answers yes with the action and starts on it when nothing is destroyed', async () => {
		const answer = confirm({ message: 'Publish now?', action: 'Publish' });

		expect(open().dataset.size).toBe('compact');
		expect(open().querySelector('.cms-modal-title')!.textContent).toBe('Publish');
		expect(open().querySelector('.cms-confirm-question')!.textContent).toBe('Publish now?');
		expect(document.activeElement).toBe(footer('primary'));
		footer('primary').click();

		await expect(answer).resolves.toBe(true);
		expect(document.querySelector('dialog')).toBeNull();
	});

	it('starts a destructive question on Cancel and answers no to every dismissal', async () => {
		const cancelled = confirm({ message: 'Delete?', title: 'Delete file', tone: 'danger' });
		expect(open().querySelector('.cms-modal-title')!.textContent).toBe('Delete file');
		expect(footer('danger').classList.contains('cms-button-solid')).toBe(true);
		expect(document.activeElement).toBe(footer('secondary'));
		footer('secondary').click();

		const closed = confirm({ message: 'Delete?' });
		open().querySelector<HTMLElement>('.cms-modal-close')!.click();

		const escaped = confirm({ message: 'Delete?' });
		open().dispatchEvent(new Event('cancel', { cancelable: true }));

		await expect(Promise.all([cancelled, closed, escaped])).resolves.toEqual([false, false, false]);
	});

	it('answers no when its owner leaves the document', async () => {
		document.body.innerHTML = '<div><button type="button">Owner</button></div>';
		const owner = document.querySelector('button')!;
		const answer = confirm({ message: 'Delete?', owner });
		owner.remove();

		await expect(answer).resolves.toBe(false);
	});
});

describe('alert', () => {
	it('resolves once acknowledged', async () => {
		const done = alert({ title: 'Error', message: 'Too big' });

		expect(open().querySelector('.cms-modal-body')!.textContent).toBe('Too big');
		expect(document.activeElement).toBe(footer('primary'));
		footer('primary').click();

		await expect(done).resolves.toBeUndefined();
	});
});

describe('question', () => {
	it('reads title, action and tone from an element', () => {
		document.body.innerHTML =
			'<form data-confirm-title="Revoke token" data-confirm-action="Revoke" data-confirm-tone="danger"></form>';
		const form = document.querySelector('form')!;

		expect(question(form, 'Revoke?')).toEqual({
			message: 'Revoke?',
			title: 'Revoke token',
			action: 'Revoke',
			tone: 'danger',
			owner: form,
		});
		expect(question(document.createElement('form'), 'Go?').tone).toBe('primary');
	});
});
