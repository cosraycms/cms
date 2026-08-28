// Menus area behavior: tree collapse, the item form's type sections,
// and the search pickers for nodes and assets. Document-level delegated
// listeners, so htmx swaps cannot orphan them. The pickers talk to the
// JSON search endpoints the element carries in `data-menu-picker-url`;
// without JavaScript the hidden uid input simply keeps its value.

type PickerItem = { uid: string; label: string; sub: string };

const timers = new WeakMap<HTMLInputElement, ReturnType<typeof setTimeout>>();
const aborters = new WeakMap<HTMLInputElement, AbortController>();

function collapse(target: Element): boolean {
	const toggle = target.closest('[data-menu-collapse]');

	if (!(toggle instanceof HTMLElement)) {
		return false;
	}

	const node = toggle.closest('.menu-node');

	if (!node) {
		return false;
	}

	const collapsed = node.classList.toggle('is-collapsed');
	toggle.setAttribute('aria-expanded', String(!collapsed));

	return true;
}

function picker(el: Element): HTMLElement | null {
	return el.closest('[data-menu-picker]');
}

function resultsOf(box: HTMLElement): HTMLElement | null {
	return box.querySelector<HTMLElement>('[data-menu-picker-results]');
}

function parse(box: HTMLElement, payload: unknown): PickerItem[] {
	const key = box.dataset.menuPicker ?? '';
	const rows =
		payload && typeof payload === 'object' ? (payload as Record<string, unknown>)[key] : null;

	if (!Array.isArray(rows)) {
		return [];
	}

	return rows.flatMap((row) => {
		if (!row || typeof row !== 'object') {
			return [];
		}

		const item = row as Record<string, unknown>;
		const uid = typeof item.uid === 'string' ? item.uid : '';
		const label =
			typeof item.title === 'string'
				? item.title
				: typeof item.filename === 'string'
					? item.filename
					: '';
		const sub =
			typeof item.typeLabel === 'string'
				? item.typeLabel
				: typeof item.kind === 'string'
					? item.kind
					: '';

		return uid === '' ? [] : [{ uid, label, sub }];
	});
}

function render(box: HTMLElement, items: PickerItem[]): void {
	const list = resultsOf(box);

	if (!list) {
		return;
	}

	list.textContent = '';

	for (const item of items) {
		const option = document.createElement('button');
		option.type = 'button';
		option.dataset.menuPickerOption = item.uid;
		option.dataset.menuPickerLabel = item.label;

		const label = document.createElement('strong');
		label.textContent = item.label;
		option.append(label);

		if (item.sub !== '') {
			const sub = document.createElement('small');
			sub.textContent = item.sub;
			option.append(sub);
		}

		list.append(option);
	}

	list.hidden = items.length === 0;
}

async function search(input: HTMLInputElement): Promise<void> {
	const box = picker(input);
	const url = box?.dataset.menuPickerUrl ?? '';

	if (!box || url === '') {
		return;
	}

	aborters.get(input)?.abort();
	const aborter = new AbortController();
	aborters.set(input, aborter);

	try {
		const response = await fetch(`${url}&q=${encodeURIComponent(input.value.trim())}`, {
			signal: aborter.signal,
			headers: { Accept: 'application/json' },
		});

		if (!response.ok) {
			return;
		}

		render(box, parse(box, await response.json()));
	} catch {
		// Aborted or failed searches leave the results as they are.
	}
}

function onInput(event: Event): void {
	const input = event.target;

	if (!(input instanceof HTMLInputElement) || !input.matches('[data-menu-picker-search]')) {
		return;
	}

	// Typing invalidates the previous selection; picking writes it back.
	const value = picker(input)?.querySelector<HTMLInputElement>('[data-menu-picker-value]');

	if (value) {
		value.value = '';
	}

	const timer = timers.get(input);

	if (timer !== undefined) {
		clearTimeout(timer);
	}

	timers.set(
		input,
		setTimeout(() => void search(input), 250),
	);
}

function onChange(event: Event): void {
	const select = event.target;

	if (!(select instanceof HTMLSelectElement) || !select.matches('[data-menu-type]')) {
		return;
	}

	const form = select.closest('form');

	form?.querySelectorAll<HTMLElement>('[data-menu-section]').forEach((section) => {
		const types = (section.dataset.menuSection ?? '').split(' ');
		section.hidden = !types.includes(select.value);
	});
}

function pick(option: HTMLElement): void {
	const box = picker(option);

	if (!box) {
		return;
	}

	const value = box.querySelector<HTMLInputElement>('[data-menu-picker-value]');
	const input = box.querySelector<HTMLInputElement>('[data-menu-picker-search]');

	if (value) {
		value.value = option.dataset.menuPickerOption ?? '';
	}

	if (input) {
		input.value = option.dataset.menuPickerLabel ?? '';
	}

	const list = resultsOf(box);

	if (list) {
		list.hidden = true;
		list.textContent = '';
	}
}

function onClick(event: Event): void {
	const target = event.target;

	if (!(target instanceof Element)) {
		return;
	}

	if (collapse(target)) {
		return;
	}

	const option = target.closest('[data-menu-picker-option]');

	if (option instanceof HTMLElement) {
		pick(option);

		return;
	}

	// A click outside any picker closes every open result list.
	if (!target.closest('[data-menu-picker]')) {
		document.querySelectorAll<HTMLElement>('[data-menu-picker-results]').forEach((list) => {
			list.hidden = true;
		});
	}
}

function onKeydown(event: KeyboardEvent): void {
	const target = event.target;

	if (
		event.key === 'Enter' &&
		target instanceof HTMLInputElement &&
		target.matches('[data-menu-picker-search]')
	) {
		// Enter must not submit the form with a half-typed search.
		event.preventDefault();
	}
}

export function install(): () => void {
	document.addEventListener('click', onClick);
	document.addEventListener('input', onInput);
	document.addEventListener('change', onChange);
	document.addEventListener('keydown', onKeydown);

	return () => {
		document.removeEventListener('click', onClick);
		document.removeEventListener('input', onInput);
		document.removeEventListener('change', onChange);
		document.removeEventListener('keydown', onKeydown);
	};
}
