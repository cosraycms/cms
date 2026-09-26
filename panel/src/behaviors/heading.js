/**
 * @param {Element} heading
 */
function refresh(heading) {
	const select = /** @type {HTMLSelectElement | null} */ (heading.querySelector('.level select'));
	const caption = heading.querySelector('[data-heading-caption]');
	if (!select || !caption) return;

	caption.textContent = `H${select.value}`;
	for (const choice of /** @type {NodeListOf<HTMLButtonElement>} */ (
		heading.querySelectorAll('[data-heading-level]')
	)) {
		const active = choice.dataset.headingLevel === select.value;
		choice.classList.toggle('is-active', active);
		choice.setAttribute('aria-checked', String(active));
	}
}

/**
 * @param {MouseEvent} event
 */
function click(event) {
	const target = event.target;
	if (!(target instanceof Element)) return;

	const choice = /** @type {HTMLButtonElement | null} */ (
		target.closest('button[data-heading-level]')
	);
	const heading = choice?.closest('[data-heading]');
	const select = /** @type {HTMLSelectElement | null} */ (heading?.querySelector('.level select'));
	if (!choice || !heading || !select || choice.disabled || select.disabled) return;
	if (heading.closest('[inert], [data-readonly="true"]')) return;

	const value = choice.dataset.headingLevel;
	if (
		!value ||
		select.value === value ||
		![...select.options].some((option) => option.value === value)
	)
		return;

	select.value = value;
	select.dispatchEvent(new Event('change', { bubbles: true }));
}

/**
 * @param {Event} event
 */
function change(event) {
	const target = event.target;
	if (!(target instanceof HTMLSelectElement)) return;

	const heading = target.closest('[data-heading]');
	if (heading) refresh(heading);
}

/**
 * @param {Event} event
 */
function stamp(event) {
	if (event.target instanceof Element) {
		event.target.querySelectorAll('[data-heading]').forEach(refresh);
	}
}

/** @returns {() => void} */
export function install() {
	document.addEventListener('click', click);
	document.addEventListener('change', change);
	document.addEventListener('repeater:stamp', stamp);

	return () => {
		document.removeEventListener('click', click);
		document.removeEventListener('change', change);
		document.removeEventListener('repeater:stamp', stamp);
	};
}
