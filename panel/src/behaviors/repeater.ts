// Repeater rows: add clones the server-rendered template, remove drops
// the row; renumbering keeps input names, ids and row labels dense so
// submissions stay ordered (the server normalizes gaps anyway).

const escapeRegex = (value: string) => value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

function renumber(container: HTMLElement): void {
	const nameBase = container.dataset.name ?? '';
	const idBase = container.dataset.id ?? '';
	const namePattern = new RegExp(`^${escapeRegex(nameBase)}\\[(?:\\d+|__i__)\\]`);
	const idPattern = new RegExp(`^${escapeRegex(idBase)}-(?:\\d+|__i__)`);
	const rows = container.querySelectorAll<HTMLElement>(':scope > [data-repeater-row]');

	rows.forEach((row, index) => {
		row.querySelectorAll<HTMLElement>('[name]').forEach((el) => {
			const name = el.getAttribute('name') ?? '';
			el.setAttribute('name', name.replace(namePattern, `${nameBase}[${index}]`));
		});
		row.querySelectorAll<HTMLElement>('[id]').forEach((el) => {
			el.id = el.id.replace(idPattern, `${idBase}-${index}`);
		});
		row.querySelectorAll<HTMLElement>('label[for]').forEach((el) => {
			const target = el.getAttribute('for') ?? '';
			el.setAttribute('for', target.replace(idPattern, `${idBase}-${index}`));
		});

		const label = row.querySelector('[data-repeater-label]');

		if (label) {
			label.textContent = `${index + 1}.`;
		}
	});

	const max = Number(container.dataset.max ?? '');
	const add = container.querySelector<HTMLElement>('[data-repeater-add]');

	if (add) {
		add.hidden = Number.isFinite(max) && max > 0 && rows.length >= max;
	}
}

function changed(container: HTMLElement): void {
	renumber(container);
	container.dispatchEvent(new Event('change', { bubbles: true }));
}

function add(container: HTMLElement): void {
	const template = container.querySelector<HTMLTemplateElement>(
		':scope > template[data-repeater-template]',
	);
	const anchor = container.querySelector(':scope > [data-repeater-footer]');

	if (!template || !anchor) {
		return;
	}

	anchor.before(template.content.cloneNode(true));
	changed(container);
}

function onClick(event: Event): void {
	const target = event.target;

	if (!(target instanceof Element)) {
		return;
	}

	const remove = target.closest('[data-repeater-remove]');

	if (remove) {
		const container = remove.closest<HTMLElement>('[data-repeater]');
		remove.closest('[data-repeater-row]')?.remove();

		if (container) {
			changed(container);
		}

		return;
	}

	const adder = target.closest('[data-repeater-add]');
	const container = adder?.closest<HTMLElement>('[data-repeater]');

	if (container) {
		add(container);
	}
}

export function install(): () => void {
	document.addEventListener('click', onClick);

	return () => {
		document.removeEventListener('click', onClick);
	};
}
