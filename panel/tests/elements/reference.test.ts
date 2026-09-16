import { tick } from 'svelte';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import type { HostPayload } from '../../src/lib/host';
import '../../src/elements/reference/ReferenceElement.svelte';

vi.mock('$lib/locale', () => ({
	__: (id: string, params?: { count: number }) => (params ? `${id}: ${params.count}` : id),
}));
vi.mock('$lib/runtime', () => ({ panelBase: () => '/cp/' }));

const fetchMock = vi.fn<typeof fetch>();

function page(uids: string[], more = false): Response {
	return Response.json({
		ok: true,
		nodes: uids.map((uid) => ({
			uid,
			title: `Entry ${uid}`,
			type: 'article',
			typeLabel: 'Article',
		})),
		more,
	});
}

async function settle(): Promise<void> {
	await vi.advanceTimersByTimeAsync(0);
	await tick();
}

async function picker(field: Record<string, unknown> = {}, uids: string[] = []) {
	const element = document.createElement('cosray-reference') as HTMLElement &
		HostPayload & { node: string };
	Object.assign(element, {
		field: { ownerType: 'page', name: 'related', label: 'Related entries', ...field },
		value: { zxx: uids.map((uid) => ({ uid })) },
		node: 'owner',
	});
	const changes = vi.fn();
	element.addEventListener('cosray-change', (event) => {
		changes(JSON.parse(JSON.stringify((event as CustomEvent).detail)));
	});
	document.body.append(element);
	await settle();

	return { element, changes, input: element.querySelector<HTMLInputElement>('[role="combobox"]')! };
}

function options(element: HTMLElement): HTMLButtonElement[] {
	return [...element.querySelectorAll<HTMLButtonElement>('[role="option"]')];
}

function button(element: HTMLElement, text: string): HTMLButtonElement {
	const match = [...element.querySelectorAll<HTMLButtonElement>('button')].find(
		(button) => button.textContent?.trim() === text,
	);
	expect(match, text).toBeDefined();

	return match!;
}

function params(call = fetchMock.mock.calls.length - 1): URLSearchParams {
	return new URL(String(fetchMock.mock.calls[call][0]), 'http://localhost').searchParams;
}

async function enter(input: HTMLInputElement, value: string): Promise<void> {
	input.value = value;
	input.dispatchEvent(new Event('input', { bubbles: true }));
	await tick();
}

async function key(target: HTMLElement, key: string): Promise<KeyboardEvent> {
	const event = new KeyboardEvent('keydown', { key, bubbles: true, cancelable: true });
	target.dispatchEvent(event);
	await settle();

	return event;
}

beforeEach(() => {
	vi.useFakeTimers();
	fetchMock.mockReset().mockImplementation(async () => page([]));
	vi.stubGlobal('fetch', fetchMock);
	HTMLElement.prototype.scrollIntoView = vi.fn();
});

afterEach(async () => {
	document.body.replaceChildren();
	await settle();
	vi.useRealTimers();
	vi.unstubAllGlobals();
});

describe('reference browsing', () => {
	it('loads a bounded first page on focus without changing the field', async () => {
		fetchMock.mockResolvedValue(page(['a', 'b'], true));
		const { element, input, changes } = await picker();
		expect(fetchMock).not.toHaveBeenCalled();

		input.focus();
		await settle();

		expect(Object.fromEntries(params())).toEqual({
			type: 'page',
			field: 'related',
			node: 'owner',
			q: '',
			offset: '0',
			limit: '30',
		});
		expect(input.getAttribute('aria-expanded')).toBe('true');
		expect(options(element).map((option) => option.textContent?.trim())).toEqual([
			'Entry a Article',
			'Entry b Article',
		]);
		expect(button(element, 'common:load-more')).toBeDefined();
		expect(changes).not.toHaveBeenCalled();
	});

	it('pages past selected rows and keeps multiple selection open until full', async () => {
		fetchMock.mockResolvedValueOnce(page(['a']));
		const { element, input, changes } = await picker({ limit: { max: 3 } }, ['a']);
		fetchMock.mockResolvedValueOnce(page(['a', 'b'], true));
		input.focus();
		await settle();
		expect(options(element)).toHaveLength(1);

		options(element)[0].click();
		await settle();
		expect(changes).toHaveBeenLastCalledWith({ value: { zxx: [{ uid: 'a' }, { uid: 'b' }] } });
		expect(input.getAttribute('aria-expanded')).toBe('true');
		expect(element.querySelector('[role="status"]')?.textContent).toContain(
			'reference:loaded-selected',
		);

		fetchMock.mockResolvedValueOnce(page(['c', 'd']));
		button(element, 'common:load-more').click();
		await settle();
		expect(params().get('offset')).toBe('2');
		expect(options(element)).toHaveLength(2);

		options(element)[0].click();
		await settle();
		expect(changes).toHaveBeenLastCalledWith({
			value: { zxx: [{ uid: 'a' }, { uid: 'b' }, { uid: 'c' }] },
		});
		expect(element.querySelector('[role="combobox"]')).toBeNull();
		expect(document.activeElement?.getAttribute('aria-label')).toBe('common:remove');
	});

	it('searches beyond loaded rows and restores browsing when the query is cleared', async () => {
		fetchMock.mockResolvedValueOnce(page(['a'], true));
		const { element, input } = await picker();
		input.focus();
		await settle();

		fetchMock.mockResolvedValueOnce(page(['remote']));
		await enter(input, ' Remote ');
		expect(options(element)).toHaveLength(0);
		await vi.advanceTimersByTimeAsync(200);
		await tick();
		expect(params().get('q')).toBe('Remote');
		expect(params().get('offset')).toBe('0');
		expect(options(element)[0].textContent).toContain('Entry remote');

		fetchMock.mockResolvedValueOnce(page(['a'], true));
		await enter(input, '');
		await settle();
		expect(params().get('q')).toBe('');
		expect(params().get('offset')).toBe('0');
		expect(input.getAttribute('aria-expanded')).toBe('true');
		expect(options(element)[0].textContent).toContain('Entry a');
	});

	it('returns to recent entries after selecting a search result', async () => {
		fetchMock.mockResolvedValueOnce(page(['a']));
		const { element, input, changes } = await picker();
		input.focus();
		await settle();
		fetchMock.mockResolvedValueOnce(page(['remote']));
		await enter(input, 'remote');
		await vi.advanceTimersByTimeAsync(200);

		fetchMock.mockResolvedValueOnce(page(['remote', 'a']));
		options(element)[0].click();
		await settle();
		expect(input.value).toBe('');
		expect(document.activeElement).toBe(input);
		expect(params().get('q')).toBe('');
		expect(options(element)).toHaveLength(1);
		expect(changes).toHaveBeenLastCalledWith({ value: { zxx: [{ uid: 'remote' }] } });
	});

	it('ignores old responses as soon as typing starts, even during the debounce', async () => {
		const pending = Promise.withResolvers<Response>();
		fetchMock.mockReturnValueOnce(pending.promise);
		const { element, input } = await picker();
		input.focus();
		await settle();
		const signal = fetchMock.mock.calls[0][1]?.signal;

		await enter(input, 'new');
		expect(signal?.aborted).toBe(true);
		pending.resolve(page(['old']));
		await settle();
		expect(options(element)).toHaveLength(0);
		expect(element.querySelector('[role="status"]')?.textContent).toContain('common:loading');

		fetchMock.mockResolvedValueOnce(page(['new']));
		await vi.advanceTimersByTimeAsync(200);
		await tick();
		expect(options(element)[0].textContent).toContain('Entry new');
	});

	it.each(['escape', 'blur', 'outside', 'unmount'])(
		'cancels pending browsing on %s',
		async (dismiss) => {
			const pending = Promise.withResolvers<Response>();
			fetchMock.mockReturnValueOnce(pending.promise);
			const { element, input, changes } = await picker();
			input.focus();
			await settle();
			const signal = fetchMock.mock.calls[0][1]?.signal;

			if (dismiss === 'escape') await key(input, 'Escape');
			if (dismiss === 'blur') input.blur();
			if (dismiss === 'outside')
				document.body.dispatchEvent(new Event('pointerdown', { bubbles: true }));
			if (dismiss === 'unmount') element.remove();
			await settle();
			expect(signal?.aborted).toBe(true);

			pending.resolve(page(['late']));
			await settle();
			expect(document.querySelector('[aria-expanded="true"]')).toBeNull();
			expect(changes).not.toHaveBeenCalled();
		},
	);

	it('cancels a debounced search when dismissed and reopens on click', async () => {
		const { input } = await picker();
		input.focus();
		await settle();
		await enter(input, 'pending');
		await key(input, 'Escape');
		await vi.advanceTimersByTimeAsync(200);
		expect(fetchMock).toHaveBeenCalledTimes(1);

		input.click();
		await vi.advanceTimersByTimeAsync(200);
		expect(fetchMock).toHaveBeenCalledTimes(2);
		expect(params().get('q')).toBe('pending');
	});

	it.each(['http', 'application', 'network'])(
		'offers retry after a %s failure rather than an empty result',
		async (failure) => {
			if (failure === 'http') fetchMock.mockResolvedValueOnce(new Response('', { status: 503 }));
			if (failure === 'application') fetchMock.mockResolvedValueOnce(Response.json({ ok: false }));
			if (failure === 'network') fetchMock.mockRejectedValueOnce(new TypeError('Offline'));
			const { element, input } = await picker();
			input.focus();
			await settle();
			expect(element.querySelector('[role="status"]')?.textContent).toContain('reference:failed');

			fetchMock.mockResolvedValueOnce(page(['a']));
			const retry = button(element, 'common:retry');
			retry.focus();
			retry.click();
			await settle();
			expect(params().get('offset')).toBe('0');
			expect(options(element)[0].textContent).toContain('Entry a');
			expect(document.activeElement).toBe(input);
		},
	);

	it('retains earlier pages on failure and retries the same offset without duplicate choices', async () => {
		fetchMock.mockResolvedValueOnce(page(['a', 'b'], true));
		const { element, input } = await picker();
		input.focus();
		await settle();
		fetchMock.mockRejectedValueOnce(new TypeError('Offline'));
		button(element, 'common:load-more').click();
		await settle();
		expect(params().get('offset')).toBe('2');
		expect(options(element)).toHaveLength(2);

		fetchMock.mockResolvedValueOnce(page(['b', 'c']));
		button(element, 'common:retry').click();
		await settle();
		expect(params().get('offset')).toBe('2');
		expect(options(element).map((option) => option.textContent?.trim())).toEqual([
			'Entry a Article',
			'Entry b Article',
			'Entry c Article',
		]);
	});

	it('distinguishes an empty eligible set from a search without matches', async () => {
		const { element, input } = await picker();
		input.focus();
		await settle();
		expect(element.querySelector('[role="status"]')?.textContent).toContain('reference:empty');
		await enter(input, 'missing');
		await vi.advanceTimersByTimeAsync(200);
		await tick();
		expect(element.querySelector('[role="status"]')?.textContent).toContain('search:no-results');
	});

	it('navigates and selects through the combobox without submitting or moving DOM focus', async () => {
		fetchMock.mockResolvedValueOnce(page(['a', 'b'], true));
		const { element, input, changes } = await picker();
		input.focus();
		await settle();
		expect((await key(input, 'Enter')).defaultPrevented).toBe(true);
		expect(changes).not.toHaveBeenCalled();

		await key(input, 'ArrowUp');
		const active = document.getElementById(input.getAttribute('aria-activedescendant')!);
		expect(active?.textContent).toContain('Entry b');
		expect(active?.getAttribute('aria-selected')).toBe('true');
		expect(active?.scrollIntoView).toHaveBeenCalledWith({ block: 'nearest' });
		expect(document.activeElement).toBe(input);
		expect(options(element).every((option) => option.tabIndex === -1)).toBe(true);

		await key(input, 'Enter');
		expect(changes).toHaveBeenLastCalledWith({ value: { zxx: [{ uid: 'b' }] } });
		const more = button(element, 'common:load-more');
		more.focus();
		await settle();
		expect(input.getAttribute('aria-expanded')).toBe('true');
		await key(more, 'Escape');
		expect(input.getAttribute('aria-expanded')).toBe('false');
		expect(document.activeElement).toBe(input);
	});

	it('gives each field its own labelled listbox', async () => {
		const first = await picker();
		const second = await picker({ label: 'Other entries' });
		const firstList = document.getElementById(first.input.getAttribute('aria-controls')!);
		const secondList = document.getElementById(second.input.getAttribute('aria-controls')!);
		expect(firstList?.getAttribute('aria-label')).toBe('Related entries');
		expect(secondList?.getAttribute('aria-label')).toBe('Other entries');
		expect(firstList).not.toBe(secondList);
		expect(first.element.contains(firstList)).toBe(true);
		expect(second.element.contains(secondList)).toBe(true);
	});

	it('does not let late label resolution restore removed references or discard new ones', async () => {
		const labels = Promise.withResolvers<Response>();
		fetchMock.mockReturnValueOnce(labels.promise);
		const { element, changes } = await picker({}, ['old']);
		fetchMock.mockResolvedValueOnce(page(['new']));
		element.querySelector<HTMLButtonElement>('[aria-label="common:remove"]')!.click();
		await settle();
		options(element)[0].click();
		await settle();
		labels.resolve(page(['old']));
		await settle();

		expect(changes).toHaveBeenLastCalledWith({ value: { zxx: [{ uid: 'new' }] } });
		const selected = element.querySelectorAll('[aria-label="common:remove"]');
		expect(selected).toHaveLength(1);
		expect(selected[0].parentElement?.textContent).toContain('Entry new');
	});

	it.each([{ immutable: true }, { limit: { max: 2 } }])(
		'does not offer additions for a readonly or full field: %j',
		async (field) => {
			fetchMock.mockResolvedValueOnce(page(['a', 'b']));
			const { element, changes } = await picker(field, ['a', 'b']);
			expect(element.querySelector('[role="combobox"]')).toBeNull();
			expect(fetchMock).toHaveBeenCalledTimes(1);
			expect(String(fetchMock.mock.calls[0][0])).toContain('/reference/labels?');
			expect(changes).not.toHaveBeenCalled();
		},
	);
});

describe('single references', () => {
	it('replaces a selected reference directly without emitting an empty intermediate value', async () => {
		fetchMock.mockResolvedValueOnce(page(['a']));
		const { element, input, changes } = await picker({ limit: { max: 1 } }, ['a']);
		expect(input.value).toBe('Entry a');

		fetchMock.mockResolvedValueOnce(page(['a', 'b']));
		input.focus();
		await settle();
		expect(params().get('q')).toBe('');
		expect(options(element)[0].getAttribute('aria-selected')).toBe('true');

		await key(input, 'ArrowDown');
		await key(input, 'ArrowDown');
		expect(options(element)[0].getAttribute('aria-selected')).toBe('true');
		expect(options(element)[1].getAttribute('aria-selected')).toBe('false');
		expect(changes).not.toHaveBeenCalled();
		await key(input, 'Enter');

		expect(changes).toHaveBeenCalledExactlyOnceWith({ value: { zxx: [{ uid: 'b' }] } });
		expect(input.value).toBe('Entry b');
		expect(input.getAttribute('aria-expanded')).toBe('false');
		expect(document.activeElement).toBe(input);

		fetchMock.mockResolvedValueOnce(page(['a', 'b']));
		await key(input, 'Enter');
		expect(input.getAttribute('aria-expanded')).toBe('true');
		expect(options(element)[1].getAttribute('aria-selected')).toBe('true');
	});

	it.each(['escape', 'blur', 'outside', 'toggle'])(
		'keeps the selected value when a search is dismissed by %s',
		async (dismiss) => {
			fetchMock.mockResolvedValueOnce(page(['a']));
			const { element, input, changes } = await picker({ limit: { max: 1 } }, ['a']);
			input.focus();
			await settle();
			await enter(input, 'another entry');
			await vi.advanceTimersByTimeAsync(200);
			await tick();
			expect(input.value).toBe('another entry');

			if (dismiss === 'escape') await key(input, 'Escape');
			if (dismiss === 'blur') input.blur();
			if (dismiss === 'outside')
				document.body.dispatchEvent(new Event('pointerdown', { bubbles: true }));
			if (dismiss === 'toggle')
				element.querySelector<HTMLButtonElement>('[aria-label="common:close"]')!.click();
			await settle();

			expect(input.value).toBe('Entry a');
			expect(input.getAttribute('aria-expanded')).toBe('false');
			expect(changes).not.toHaveBeenCalled();
		},
	);

	it('clears explicitly, restores recent choices, and allows selecting again', async () => {
		fetchMock.mockResolvedValueOnce(page(['a']));
		const { element, input, changes } = await picker({ limit: { max: 1 } }, ['a']);
		input.focus();
		await settle();
		await enter(input, 'something');

		fetchMock.mockResolvedValueOnce(page(['a', 'b']));
		const clear = element.querySelector<HTMLButtonElement>('[aria-label="common:remove"]')!;
		clear.focus();
		clear.click();
		await settle();

		expect(changes).toHaveBeenCalledExactlyOnceWith({ value: { zxx: [] } });
		expect(input.value).toBe('');
		expect(params().get('q')).toBe('');
		expect(input.getAttribute('aria-expanded')).toBe('true');
		expect(document.activeElement).toBe(input);

		options(element)[0].click();
		await settle();
		expect(changes).toHaveBeenLastCalledWith({ value: { zxx: [{ uid: 'a' }] } });
		expect(input.value).toBe('Entry a');
		expect(input.getAttribute('aria-expanded')).toBe('false');
	});

	it('closes without dirtying the field when the same reference is chosen', async () => {
		fetchMock.mockResolvedValueOnce(page(['a']));
		const { element, input, changes } = await picker({ limit: { max: 1 } }, ['a']);
		fetchMock.mockResolvedValueOnce(page(['a']));
		element.querySelector<HTMLButtonElement>('[aria-label="common:open"]')!.click();
		await settle();
		expect(document.activeElement).toBe(input);

		options(element)[0].click();
		await settle();
		expect(changes).not.toHaveBeenCalled();
		expect(input.value).toBe('Entry a');
		expect(input.getAttribute('aria-expanded')).toBe('false');
	});

	it('does not mistake uncommitted search text for a selected reference', async () => {
		const { input, changes } = await picker({ limit: { max: 1 } });
		input.focus();
		await settle();
		await enter(input, 'not a selection');
		await key(input, 'Enter');
		expect(changes).not.toHaveBeenCalled();
		input.blur();
		await settle();
		expect(input.value).toBe('');
	});

	it('shows an immutable selection as a read-only value without querying choices', async () => {
		fetchMock.mockResolvedValueOnce(page(['a']));
		const { element, changes } = await picker({ limit: { max: 1 }, immutable: true }, ['a']);
		const input = element.querySelector<HTMLInputElement>('input')!;
		expect(input.readOnly).toBe(true);
		expect(input.value).toBe('Entry a');
		input.focus();
		await key(input, 'ArrowDown');
		await key(input, 'Enter');
		expect(fetchMock).toHaveBeenCalledTimes(1);
		expect(changes).not.toHaveBeenCalled();
	});
});
