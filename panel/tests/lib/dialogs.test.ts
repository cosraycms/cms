import { afterEach, describe, expect, it, vi } from 'vitest';
import { closeDialog, openDialog } from '../../src/lib/dialogs';

vi.mock('$lib/locale', () => ({ __: (id: string) => id }));

afterEach(() => {
	for (const dialog of document.querySelectorAll('dialog')) closeDialog(dialog);
	document.body.replaceChildren();
});

function fixture() {
	const owner = document.createElement('section');
	owner.innerHTML =
		'<button>Open</button><dialog><h2>Settings</h2><input name="title"><button data-dialog-close>Close</button></dialog>';
	document.body.append(owner);
	const opener = owner.querySelector('button')!;
	const dialog = owner.querySelector('dialog')!;
	opener.focus();
	return { owner, opener, dialog };
}

function pointer(dialog: HTMLDialogElement, type: string, x: number, y: number) {
	const event = new MouseEvent(type, { bubbles: true, clientX: x, clientY: y, button: 0 });
	Object.defineProperty(event, 'pointerId', { value: 1 });
	dialog.dispatchEvent(event);
}

describe('native dialog lifecycle', () => {
	it('closes once and returns focus to its opener', () => {
		const { opener, dialog } = fixture();
		const cleanup = vi.fn();
		const handle = openDialog(dialog, { onClose: cleanup });
		expect(document.activeElement).toBe(dialog.querySelector('input'));
		dialog.querySelector<HTMLElement>('[data-dialog-close]')!.click();
		handle.close();
		expect(dialog.open).toBe(false);
		expect(cleanup).toHaveBeenCalledOnce();
		expect(document.activeElement).toBe(opener);
	});

	it('accepts browser-driven closure and can reopen before the queued close event', async () => {
		const { dialog } = fixture();
		const cleanup = vi.fn();
		openDialog(dialog, { onClose: cleanup });
		dialog.close();
		await Promise.resolve();
		expect(cleanup).toHaveBeenCalledOnce();
		openDialog(dialog);
		closeDialog(dialog);
		openDialog(dialog);
		await Promise.resolve();
		expect(dialog.open).toBe(true);
	});

	it('uses the designated safe action instead of the destructive first button', () => {
		const { dialog } = fixture();
		dialog.innerHTML =
			'<h2>Delete?</h2><button>Delete</button><button data-dialog-focus>Cancel</button>';
		openDialog(dialog);
		expect(document.activeElement?.textContent).toBe('Cancel');
	});

	it('does not refocus a hidden or disabled opener', () => {
		const { dialog, opener } = fixture();
		openDialog(dialog);
		opener.disabled = true;
		closeDialog(dialog);
		expect(document.activeElement).not.toBe(opener);
	});

	it('only dismisses a gesture that starts and ends on the backdrop', () => {
		const { dialog } = fixture();
		vi.spyOn(dialog, 'getBoundingClientRect').mockReturnValue(new DOMRect(20, 20, 100, 100));
		openDialog(dialog);
		pointer(dialog, 'pointerdown', 40, 40);
		pointer(dialog, 'pointerup', 0, 0);
		expect(dialog.open).toBe(true);
		pointer(dialog, 'pointerdown', 0, 0);
		pointer(dialog, 'pointerup', 40, 40);
		expect(dialog.open).toBe(true);
		pointer(dialog, 'pointerdown', 0, 0);
		pointer(dialog, 'pointercancel', 0, 0);
		pointer(dialog, 'pointerup', 0, 0);
		expect(dialog.open).toBe(true);
		pointer(dialog, 'pointerdown', 0, 0);
		pointer(dialog, 'pointerup', 0, 0);
		expect(dialog.open).toBe(false);
	});

	it('keeps nested dialog keys away from the underlying editor', () => {
		const { dialog: parent, opener } = fixture();
		const editorKey = vi.fn();
		document.addEventListener('keydown', editorKey);
		try {
			openDialog(parent);
			const input = parent.querySelector('input')!;
			const child = document.createElement('dialog');
			child.innerHTML = '<h2>Nested</h2><input>';
			document.body.append(child);
			openDialog(child);
			child
				.querySelector('input')!
				.dispatchEvent(new KeyboardEvent('keydown', { key: '/', bubbles: true }));
			expect(editorKey).not.toHaveBeenCalled();
			child.dispatchEvent(new Event('cancel', { cancelable: true }));
			expect(child.open).toBe(false);
			expect(parent.open).toBe(true);
			expect(document.activeElement).toBe(input);
			closeDialog(parent);
			expect(document.activeElement).toBe(opener);
		} finally {
			document.removeEventListener('keydown', editorKey);
		}
	});

	it('closes body-mounted children when their parent closes', () => {
		const { dialog: parent } = fixture();
		openDialog(parent);
		const child = document.createElement('dialog');
		document.body.append(child);
		const cleanup = vi.fn();
		openDialog(child, { onClose: cleanup });
		closeDialog(parent);
		expect(child.open).toBe(false);
		expect(cleanup).toHaveBeenCalledOnce();
	});

	it('cleans up disconnected dialogs and refuses a stale owner', async () => {
		const { owner, dialog } = fixture();
		const cleanup = vi.fn();
		openDialog(dialog, { owner, onClose: cleanup });
		dialog.remove();
		await Promise.resolve();
		expect(cleanup).toHaveBeenCalledOnce();
		owner.remove();
		document.body.append(dialog);
		openDialog(dialog, { owner, onClose: cleanup });
		expect(dialog.open).toBe(false);
		expect(cleanup).toHaveBeenCalledTimes(2);
	});

	it('leaves intentional post-close focus alone', async () => {
		const { dialog } = fixture();
		const created = document.createElement('input');
		document.body.append(created);
		openDialog(dialog);
		closeDialog(dialog);
		created.focus();
		await Promise.resolve();
		expect(document.activeElement).toBe(created);
	});

	it('cleans up after showModal fails', () => {
		const { dialog } = fixture();
		const cleanup = vi.fn();
		vi.spyOn(dialog, 'showModal').mockImplementation(() => {
			throw new Error('cannot open');
		});
		expect(() => openDialog(dialog, { onClose: cleanup })).toThrow('cannot open');
		closeDialog(dialog);
		expect(cleanup).toHaveBeenCalledOnce();
	});
});
