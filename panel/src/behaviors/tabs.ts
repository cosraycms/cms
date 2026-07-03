// Locale tabs on field wrappers: every locale variant is rendered
// server-side; the tabs only toggle visibility and hand the editing
// locale to hosted element controls (the wrapper owns the tabs, per the
// element contract).

function activate(event: Event): void {
	const target = event.target;

	if (!(target instanceof Element)) {
		return;
	}

	const tab = target.closest('.locale-tab[data-locale-tab]');

	if (!(tab instanceof HTMLElement)) {
		return;
	}

	const field = tab.closest('.cms-field');
	const locale = tab.dataset.localeTab ?? '';

	if (!field || locale === '') {
		return;
	}

	field.querySelectorAll('.locale-tab[data-locale-tab]').forEach((other) => {
		other.classList.toggle('active', other === tab);
	});

	field.querySelectorAll<HTMLElement>('.cms-locale-variant[data-locale]').forEach((variant) => {
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
