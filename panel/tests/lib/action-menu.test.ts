import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { install, openMenu, placement } from '../../src/lib/action-menu';
import { install as installRepeater } from '../../src/behaviors/repeater';
import { openDialog } from '../../src/lib/dialogs';

let teardown: () => void;
let frames: FrameRequestCallback[];

beforeEach(() => {
	frames = [];
	vi.stubGlobal('requestAnimationFrame', (callback: FrameRequestCallback) => frames.push(callback));
	vi.stubGlobal('cancelAnimationFrame', vi.fn());
	vi.stubGlobal('innerWidth', 800);
	vi.stubGlobal('innerHeight', 600);
	teardown = install();
});

afterEach(() => {
	teardown();
	document.body.replaceChildren();
	vi.restoreAllMocks();
	vi.unstubAllGlobals();
});

function fixture(id = 'actions') {
	const owner = document.createElement('div');
	owner.innerHTML = `<button type="button" popovertarget="${id}">Actions</button>
		<div id="${id}" popover="auto" data-action-menu>
			<button type="button" disabled>Unavailable</button>
			<button type="button">First</button>
			<a href="#disabled" aria-disabled="true">Disabled link</a>
			<button type="button" hidden>Hidden</button>
			<a href="#last">Last</a>
		</div><input aria-label="Next field">`;
	document.body.append(owner);
	const trigger = owner.querySelector('button')!;
	const menu = owner.querySelector<HTMLElement>('[popover]')!;
	const first = menu.querySelectorAll('button')[1];
	const last = menu.querySelectorAll('a')[1];
	const next = owner.querySelector('input')!;
	const box = { x: 100, y: 100, width: 32, height: 24 };
	vi.spyOn(trigger, 'getBoundingClientRect').mockImplementation(
		() => new DOMRect(box.x, box.y, box.width, box.height),
	);
	Object.defineProperties(menu, {
		offsetWidth: { value: 200 },
		offsetHeight: { value: 240 },
		clientHeight: { value: 238 },
		scrollHeight: { value: 238 },
	});
	menu.style.padding = '4px';
	return { owner, trigger, menu, first, last, next, box };
}

async function key(element: Element, key: string) {
	const event = new KeyboardEvent('keydown', { key, bubbles: true, cancelable: true });
	element.dispatchEvent(event);
	await Promise.resolve();
	return event;
}

describe('action-menu keyboard and lifecycle', () => {
	it('opens on arrows and navigates only usable choices, including Home and End', async () => {
		const { trigger, menu, first, last } = fixture();
		trigger.focus();
		await key(trigger, 'ArrowDown');
		expect(document.activeElement).toBe(first);
		expect(trigger.getAttribute('aria-expanded')).toBe('true');
		expect(trigger.getAttribute('aria-haspopup')).toBe('menu');
		expect(menu.getAttribute('aria-labelledby')).toBe(trigger.id);
		await key(first, 'ArrowDown');
		expect(document.activeElement).toBe(last);
		await key(last, 'ArrowDown');
		expect(document.activeElement).toBe(first);
		await key(first, 'ArrowUp');
		expect(document.activeElement).toBe(last);
		await key(last, 'Home');
		expect(document.activeElement).toBe(first);
		await key(first, 'End');
		expect(document.activeElement).toBe(last);
		await key(last, 'Escape');
		expect(document.activeElement).toBe(trigger);
		expect(trigger.getAttribute('aria-expanded')).toBe('false');
		await key(trigger, 'ArrowUp');
		expect(document.activeElement).toBe(last);
	});

	it.each(['Enter', ' '])(
		'closes before %s activates an action without swallowing its event',
		async (keyName) => {
			const { trigger, menu, first } = fixture();
			const action = vi.fn(() => {
				expect(menu.matches(':popover-open')).toBe(false);
				expect(document.activeElement).toBe(trigger);
			});
			first.addEventListener('click', action);
			await key(trigger, 'ArrowDown');
			await key(first, keyName);
			expect(action).toHaveBeenCalledOnce();
		},
	);

	it('keeps pointer opening at the trigger and prevents activation of disabled links', async () => {
		const { trigger, menu } = fixture();
		trigger.focus();
		trigger.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, detail: 1 }));
		await Promise.resolve();
		expect(document.activeElement).toBe(trigger);
		const disabled = menu.querySelector('a')!;
		const action = vi.fn();
		disabled.addEventListener('click', action);
		const event = new MouseEvent('click', { bubbles: true, cancelable: true });
		disabled.dispatchEvent(event);
		expect(event.defaultPrevented).toBe(true);
		expect(action).not.toHaveBeenCalled();
		expect(menu.matches(':popover-open')).toBe(true);
	});

	it('lets Tab leave without redirecting focus to the trigger', async () => {
		const { trigger, menu, first, next } = fixture();
		await key(trigger, 'ArrowDown');
		const event = await key(first, 'Tab');
		expect(event.defaultPrevented).toBe(false);
		next.focus();
		expect(menu.matches(':popover-open')).toBe(false);
		expect(document.activeElement).toBe(next);
	});

	it('consumes menu Escape before it can dismiss an enclosing dialog or reach editor shortcuts', async () => {
		const { owner, trigger, first, menu } = fixture();
		const dialog = document.createElement('dialog');
		dialog.append(owner);
		document.body.append(dialog);
		const { close } = openDialog(dialog);
		const background = vi.fn();
		owner.addEventListener('keydown', background);
		await key(trigger, 'ArrowDown');
		const event = await key(first, 'Escape');
		expect(event.defaultPrevented).toBe(true);
		expect(background).not.toHaveBeenCalled();
		expect(menu.matches(':popover-open')).toBe(false);
		expect(dialog.open).toBe(true);
		close();
	});

	it('closes the previous menu and synchronizes native dismissals without reclaiming focus', async () => {
		const a = fixture('one');
		const b = fixture('two');
		await key(a.trigger, 'ArrowDown');
		await key(b.trigger, 'ArrowDown');
		expect(a.menu.matches(':popover-open')).toBe(false);
		expect(a.trigger.getAttribute('aria-expanded')).toBe('false');
		expect(document.activeElement).toBe(b.first);
		a.next.focus();
		b.menu.hidePopover();
		expect(b.trigger.getAttribute('aria-expanded')).toBe('false');
		expect(document.activeElement).toBe(a.next);
	});

	it('cleans up removed anchors and blocks stale actions before the next layout update', async () => {
		const { trigger, menu, first } = fixture();
		await key(trigger, 'ArrowDown');
		const action = vi.fn();
		first.addEventListener('click', action);
		trigger.remove();
		first.click();
		expect(action).not.toHaveBeenCalled();
		frames.at(-1)!(0);
		expect(menu.matches(':popover-open')).toBe(false);
		expect(trigger.getAttribute('aria-expanded')).toBe('false');
	});

	it('repositions for layout movement and stops when the owner becomes hidden', async () => {
		const { trigger, menu, box, owner } = fixture();
		openMenu(trigger);
		await Promise.resolve();
		expect(menu.style.top).toBe('128px');
		box.y = 550;
		frames.at(-1)!(0);
		expect(menu.style.top).toBe('306px');
		owner.hidden = true;
		frames.at(-1)!(0);
		expect(menu.matches(':popover-open')).toBe(false);
	});

	it('can be installed repeatedly without duplicate handling and reused after disposal', async () => {
		const { trigger, menu } = fixture();
		install()();
		trigger.click();
		await Promise.resolve();
		expect(menu.matches(':popover-open')).toBe(true);
		teardown();
		expect(menu.matches(':popover-open')).toBe(false);
		teardown = install();
		trigger.click();
		await Promise.resolve();
		expect(menu.matches(':popover-open')).toBe(true);
	});
});

describe('action-menu geometry', () => {
	it.each(['start', 'end', 'center'] as const)(
		'keeps %s-aligned menus within a narrow visible pane',
		(align) => {
			const { owner, trigger, menu, box } = fixture();
			owner.style.overflowX = 'auto';
			vi.spyOn(owner, 'getBoundingClientRect').mockReturnValue(new DOMRect(70, 0, 100, 600));
			Object.defineProperty(owner, 'clientWidth', { value: 100 });
			box.x = 150;
			const point = placement(trigger, menu, align);
			expect(point.left).toBe(70);
			expect(point.maxWidth).toBe(100);
			expect(point.visible).toBe(true);
		},
	);

	it('intersects the visual viewport and reports anchors scrolled out of view', () => {
		const { trigger, menu, box } = fixture();
		vi.stubGlobal('visualViewport', { offsetLeft: 100, offsetTop: 50, width: 300, height: 400 });
		box.x = 380;
		box.y = 400;
		const point = placement(trigger, menu);
		expect(point.left).toBe(200);
		expect(point.top).toBe(156);
		expect(point.maxHeight).toBe(346);
		box.y = 460;
		expect(placement(trigger, menu).visible).toBe(false);
	});
});

it.each([false, true])(
	'inserts the selected PHP-rendered block type without submitting (nested/reordered: %s)',
	async (nested) => {
		const form = document.createElement('form');
		const types = ['First', 'Second'].map((label) => ({
			type: label.toLowerCase(),
			label,
			fields: [{ name: 'text', control: { name: 'text' } }],
		}));
		form.innerHTML = execFileSync(
			'php',
			[resolve(dirname(fileURLToPath(import.meta.url)), '../../../tests/Fixtures/Panel/field.php')],
			{
				encoding: 'utf8',
				input: JSON.stringify({
					field: {
						name: 'story',
						control: {
							name: 'blocks',
							props: {
								blockTypes: nested
									? [
											{
												type: 'group',
												label: 'Group',
												fields: [
													{
														name: 'children',
														control: { name: 'blocks', props: { blockTypes: types } },
													},
												],
											},
										]
									: types,
							},
						},
					},
					data: { value: { zxx: [] } },
					locales: [{ id: 'en', title: 'English' }],
					defaultLocale: 'en',
				}),
			},
		);
		document.body.append(form);
		const submit = vi.fn((event: Event) => event.preventDefault());
		form.addEventListener('submit', submit);
		const changed = vi.fn();
		form.addEventListener('change', changed);
		const stopRepeater = installRepeater();
		try {
			let scope: ParentNode = form;
			if (nested) {
				const add = form.querySelector<HTMLButtonElement>(
					'[data-repeater-footer] > [data-repeater-add]',
				)!;
				add.click();
				add.click();
				scope = form.querySelectorAll('[data-repeater-list] > [data-repeater-row]')[1];
				scope.querySelector<HTMLButtonElement>('[data-repeater-move="up"]')!.click();
				changed.mockClear();
			}
			const trigger = scope.querySelector<HTMLButtonElement>('[popovertarget]')!;
			vi.spyOn(trigger, 'getBoundingClientRect').mockReturnValue(new DOMRect(100, 100, 100, 24));
			await key(trigger, 'ArrowDown');
			await key(document.activeElement!, 'ArrowDown');
			await key(document.activeElement!, 'Enter');
			const row = scope.querySelector<HTMLElement>('[data-repeater-list] > [data-repeater-row]')!;
			expect(row).not.toBeNull();
			expect(row.querySelector<HTMLInputElement>('input[name$="[type]"]')?.value).toBe('second');
			expect(row.contains(document.activeElement)).toBe(true);
			expect(changed).toHaveBeenCalledOnce();
			expect(submit).not.toHaveBeenCalled();
		} finally {
			stopRepeater();
		}
	},
);
