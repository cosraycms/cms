// Local derived UI: inputs that mirror another field until manually
// edited, and character counters.
//
// - data-derive="title" on an input derives its value from the named
//   sibling field (neutral or default locale), optionally through
//   data-derive-transform="slugify". Typing into the target detaches
//   it; a target whose initial value already differs starts detached.
// - <output data-count-of="field-title-zxx"> shows the character count
//   of the referenced input.

const DETACHED = 'data-derive-detached';

function slugify(value: string): string {
	return value
		.toLowerCase()
		.replaceAll('ä', 'ae')
		.replaceAll('ö', 'oe')
		.replaceAll('ü', 'ue')
		.replaceAll('ß', 'ss')
		.normalize('NFD')
		.replace(/[̀-ͯ]/g, '')
		.replace(/[^a-z0-9]+/g, '-')
		.replace(/^-+|-+$/g, '');
}

function transform(name: string, value: string): string {
	return name === 'slugify' ? slugify(value) : value;
}

function sourceValue(form: HTMLFormElement, field: string): string {
	for (const locale of ['zxx', document.documentElement.lang || 'en']) {
		const values = new FormData(form).getAll(`content[${field}][value][${locale}]`);
		const last = values.at(-1);

		if (typeof last === 'string' && last !== '') {
			return last;
		}
	}

	return '';
}

function derive(form: HTMLFormElement): void {
	form.querySelectorAll<HTMLInputElement>('input[data-derive]').forEach((target) => {
		if (target.hasAttribute(DETACHED)) {
			return;
		}

		const derived = transform(
			target.dataset.deriveTransform ?? '',
			sourceValue(form, target.dataset.derive ?? ''),
		);

		if (target.value !== '' && target.value !== derived) {
			// A pre-existing divergent value means the user chose one.
			target.setAttribute(DETACHED, 'true');

			return;
		}

		target.value = derived;
	});
}

function count(form: HTMLFormElement): void {
	form.querySelectorAll<HTMLElement>('[data-count-of]').forEach((counter) => {
		const input = document.getElementById(counter.dataset.countOf ?? '');

		if (input instanceof HTMLInputElement || input instanceof HTMLTextAreaElement) {
			counter.textContent = String(input.value.length);
		}
	});
}

function onInput(event: Event): void {
	const target = event.target;

	if (!(target instanceof Element)) {
		return;
	}

	const form = target.closest('form');

	if (!(form instanceof HTMLFormElement)) {
		return;
	}

	// Typing into a derived input detaches it from its source.
	if (target instanceof HTMLInputElement && target.hasAttribute('data-derive')) {
		target.setAttribute(DETACHED, 'true');
	}

	derive(form);
	count(form);
}

function swapped(): void {
	document.querySelectorAll('form').forEach((form) => {
		derive(form);
		count(form);
	});
}

export function install(): () => void {
	document.addEventListener('input', onInput);
	document.addEventListener('htmx:after:swap', swapped);
	swapped();

	return () => {
		document.removeEventListener('input', onInput);
		document.removeEventListener('htmx:after:swap', swapped);
	};
}
