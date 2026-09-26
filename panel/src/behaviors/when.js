// Conditional field visibility: wrappers carry their When condition as
// data-when; every form edit re-evaluates all conditions against form
// state. Hidden fields keep their inputs (and values) in the form —
// only `required` is suspended so an invisible field can never block a
// submit. The PHP evaluator (Cosray\Field\Condition) applies the exact
// same semantics to stored content at read time; keep them in lockstep.

/**
 * @typedef {object} Condition
 * @property {string} field
 * @property {string} op
 * @property {unknown} value
 */

/**
 * @param {HTMLFormElement} form
 * @param {string} field
 * @returns {string}
 */
function formValue(form, field) {
	// The last entry wins: checkbox presence markers precede the box.
	const name = `content[${field}][value][zxx]`;
	const last = new FormData(form).getAll(name).at(-1);

	// When historically treats false as empty, including nullable checkbox fields.
	if (
		last === '0' &&
		Array.from(
			/** @type {NodeListOf<HTMLInputElement>} */ (form.querySelectorAll('[data-checkbox] input')),
		).some((input) => input.name === name)
	) {
		return '';
	}

	return typeof last === 'string' ? last : '';
}

/**
 * @param {unknown} value
 * @returns {string}
 */
function normalize(value) {
	if (typeof value === 'boolean') {
		return value ? '1' : '';
	}

	return typeof value === 'string' || typeof value === 'number' ? String(value) : '';
}

/**
 * @param {Condition} condition
 * @param {string} value
 * @returns {boolean}
 */
export function active(condition, value) {
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

function apply() {
	/** @type {NodeListOf<HTMLElement>} */ (
		document.querySelectorAll('.cms-field[data-when]')
	).forEach((wrapper) => {
		const form = wrapper.closest('form');

		if (!(form instanceof HTMLFormElement)) {
			return;
		}

		/** @type {Condition} */
		let condition;

		try {
			condition = /** @type {Condition} */ (JSON.parse(wrapper.dataset.when ?? ''));
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

	/** @type {NodeListOf<HTMLFieldSetElement>} */ (
		document.querySelectorAll('.cms-fieldset')
	).forEach((fieldset) => {
		const fields = Array.from(
			/** @type {NodeListOf<HTMLElement>} */ (fieldset.querySelectorAll('.cms-field')),
		);
		fieldset.hidden = fields.length === 0 || fields.every((field) => field.hidden);
	});
}

/**
 * @param {Event} event
 */
function reapply(event) {
	if (event.target instanceof Element && event.target.closest('#node-editor-form')) {
		apply();
	}
}

function swapped() {
	apply();
}

/** @returns {() => void} */
export function install() {
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
