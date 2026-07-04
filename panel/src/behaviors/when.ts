// Conditional field visibility: wrappers carry their When condition as
// data-when; every form edit re-evaluates all conditions against form
// state. Hidden fields keep their inputs (and values) in the form —
// only `required` is suspended so an invisible field can never block a
// submit. The PHP evaluator (Cosray\Field\Condition) applies the exact
// same semantics to stored content at read time; keep them in lockstep.

type Condition = { field: string; op: string; value: unknown };

function formValue(form: HTMLFormElement, field: string): string {
	// The last entry wins: checkbox presence markers precede the box.
	const values = new FormData(form).getAll(`content[${field}][value][zxx]`);
	const last = values.at(-1);

	return typeof last === 'string' ? last : '';
}

function normalize(value: unknown): string {
	if (typeof value === 'boolean') {
		return value ? '1' : '';
	}

	return typeof value === 'string' || typeof value === 'number' ? String(value) : '';
}

export function active(condition: Condition, value: string): boolean {
	switch (condition.op) {
		case 'truthy':
			return value !== '' && value !== '0';
		case 'eq':
			return value === normalize(condition.value);
		case 'neq':
			return value !== normalize(condition.value);
		case 'in':
			return Array.isArray(condition.value) && condition.value.map(normalize).includes(value);
		case 'empty':
			return value === '';
		case 'notEmpty':
			return value !== '';
		default:
			return true;
	}
}

function apply(): void {
	document.querySelectorAll<HTMLElement>('.cms-field[data-when]').forEach((wrapper) => {
		const form = wrapper.closest('form');

		if (!(form instanceof HTMLFormElement)) {
			return;
		}

		let condition: Condition;

		try {
			condition = JSON.parse(wrapper.dataset.when ?? '') as Condition;
		} catch {
			return;
		}

		const show = active(condition, formValue(form, condition.field));
		wrapper.hidden = !show;

		// Suspend required on hidden fields, restore it when they return.
		wrapper.querySelectorAll('[required]').forEach((input) => {
			if (!show) {
				input.setAttribute('data-when-required', 'true');
				input.removeAttribute('required');
			}
		});

		if (show) {
			wrapper.querySelectorAll('[data-when-required]').forEach((input) => {
				input.setAttribute('required', '');
				input.removeAttribute('data-when-required');
			});
		}
	});
}

function reapply(event: Event): void {
	if (event.target instanceof Element && event.target.closest('#node-editor-form')) {
		apply();
	}
}

function swapped(): void {
	apply();
}

export function install(): () => void {
	document.addEventListener('input', reapply);
	document.addEventListener('change', reapply);
	document.addEventListener('cosray-change', reapply);
	document.addEventListener('htmx:after:swap', swapped);
	apply();

	return () => {
		document.removeEventListener('input', reapply);
		document.removeEventListener('change', reapply);
		document.removeEventListener('cosray-change', reapply);
		document.removeEventListener('htmx:after:swap', swapped);
	};
}
