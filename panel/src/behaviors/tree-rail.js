// Darkens the tree rail the row under the pointer or focus hangs from, and
// no other. The rows are flat siblings, so CSS cannot find a rail's pieces:
// the parent's start under its toggle, each child's segment, and the guides
// deeper rows carry for it. Depth finds them.

const ROW = '.cms-list tbody .cms-list-row';

/** @type {Element[]} */
let lit = [];

/** @type {Element | null} */
let current = null;

/**
 * @param {Element | null} row
 */
function depth(row) {
	return Number(row?.getAttribute('data-depth') ?? -1);
}

/**
 * @param {Element} row
 * @returns {Array<[Element, string]>}
 */
function pieces(row) {
	const level = depth(row);

	if (level < 1) {
		return [];
	}

	let first = row;

	while (depth(first.previousElementSibling) >= level) {
		first = /** @type {Element} */ (first.previousElementSibling);
	}

	/** @type {Array<[Element, string]>} */
	const result = [];
	const parent = first.previousElementSibling;

	if (parent && depth(parent) === level - 1) {
		result.push([parent, 'is-rail-head']);
	}

	for (
		let next = /** @type {Element | null} */ (first);
		next && depth(next) >= level;
		next = next.nextElementSibling
	) {
		if (depth(next) === level) {
			result.push([next, 'is-rail-lit']);
		} else {
			const guide = next.querySelector(`.cms-list-guide[data-guide="${level}"]`);

			if (guide) {
				result.push([guide, 'is-lit']);
			}
		}
	}

	return result;
}

function clear() {
	for (const element of lit) {
		element.classList.remove('is-rail-head', 'is-rail-lit', 'is-lit');
	}

	lit = [];
	current = null;
}

/**
 * @param {EventTarget | null} target
 */
function enter(target) {
	const row = target instanceof Element ? target.closest(ROW) : null;

	if (row === current) {
		return;
	}

	clear();
	current = row;

	if (!row) {
		return;
	}

	for (const [element, name] of pieces(row)) {
		element.classList.add(name);
		lit.push(element);
	}
}

/**
 * @param {Event} event
 */
function over(event) {
	enter(event.target);
}

/**
 * @param {PointerEvent | FocusEvent} event
 */
function out(event) {
	const related = event.relatedTarget;

	if (!(related instanceof Element) || !related.closest(ROW)) {
		clear();
	}
}

/** @returns {() => void} */
export function install() {
	document.addEventListener('pointerover', over);
	document.addEventListener('pointerout', out);
	document.addEventListener('focusin', over);
	document.addEventListener('focusout', out);

	return () => {
		clear();
		document.removeEventListener('pointerover', over);
		document.removeEventListener('pointerout', out);
		document.removeEventListener('focusin', over);
		document.removeEventListener('focusout', out);
	};
}
