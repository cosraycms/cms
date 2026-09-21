function refresh(heading: Element): void {
	const select = heading.querySelector<HTMLSelectElement>('.level select');
	const caption = heading.querySelector('[data-heading-caption]');
	if (!select || !caption) return;

	caption.textContent = `H${select.value}`;
	for (const choice of heading.querySelectorAll<HTMLButtonElement>('[data-heading-level]')) {
		const active = choice.dataset.headingLevel === select.value;
		choice.classList.toggle('is-active', active);
		choice.setAttribute('aria-checked', String(active));
	}
}

function click(event: MouseEvent): void {
	const target = event.target;
	if (!(target instanceof Element)) return;

	const choice = target.closest<HTMLButtonElement>('button[data-heading-level]');
	const heading = choice?.closest('[data-heading]');
	const select = heading?.querySelector<HTMLSelectElement>('.level select');
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

function change(event: Event): void {
	const target = event.target;
	if (!(target instanceof HTMLSelectElement)) return;

	const heading = target.closest('[data-heading]');
	if (heading) refresh(heading);
}

function stamp(event: Event): void {
	if (event.target instanceof Element) {
		event.target.querySelectorAll('[data-heading]').forEach(refresh);
	}
}

export function install(): () => void {
	document.addEventListener('click', click);
	document.addEventListener('change', change);
	document.addEventListener('repeater:stamp', stamp);

	return () => {
		document.removeEventListener('click', click);
		document.removeEventListener('change', change);
		document.removeEventListener('repeater:stamp', stamp);
	};
}
