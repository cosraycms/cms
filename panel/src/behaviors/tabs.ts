// Locale tabs: every locale variant is rendered server-side; the tabs
// only toggle visibility and hand the editing locale to hosted element
// controls (the scope owns the tabs, per the element contract). A scope
// is whatever carries data-locale-scope — a field wrapper, or a typed
// repeater row switching all of its sub-fields at once.

function activate(event: Event): void {
	const target = event.target;

	if (!(target instanceof Element)) {
		return;
	}

	const tab = target.closest('[data-locale-tab]');

	if (!(tab instanceof HTMLElement)) {
		return;
	}

	const field = tab.closest('[data-locale-scope]');
	const locale = tab.dataset.localeTab ?? '';

	if (!field || locale === '') {
		return;
	}

	field.querySelectorAll('[data-locale-tab]').forEach((other) => {
		other.classList.toggle('active', other === tab);
	});

	field.querySelectorAll<HTMLElement>('.variant[data-locale]').forEach((variant) => {
		variant.hidden = variant.dataset.locale !== locale;
	});

	field.querySelectorAll('cosray-host').forEach((host) => {
		(host as HTMLElement & { locale: string }).locale = locale;
	});
}

export function install(): () => void {
	document.addEventListener('click', activate);

	return () => {
		document.removeEventListener('click', activate);
	};
}
