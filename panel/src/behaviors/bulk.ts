// Collection bulk selection: the checkbox column drives the action bar
// (count, clear, action buttons) and the confirm dialogs. Server-rendered
// strings arrive as data-label-one/-many templates with a :count
// placeholder, so no client catalog is involved. Delegated listeners
// only; the selection deliberately resets with every htmx swap
// (per-page selection).

// The bulk redirect carries its outcome summary in a `notice` query
// param the server renders as a banner once. The param leaves the
// address bar right after, so a refresh or a copied link does not
// repeat a stale message.
function stripNotice(): void {
	const url = new URL(window.location.href);

	if (!url.searchParams.has('notice')) {
		return;
	}

	url.searchParams.delete('notice');
	history.replaceState(history.state, '', url);
}

// htmx writes the swapped-in URL to history after the swap event, so
// the strip has to run behind it.
function onSwap(): void {
	setTimeout(stripNotice, 0);
}

function boxes(): HTMLInputElement[] {
	return Array.from(document.querySelectorAll<HTMLInputElement>('input[data-bulk-check]'));
}

function selected(): HTMLInputElement[] {
	return boxes().filter((box) => box.checked);
}

function fill(target: HTMLElement, count: number): void {
	const template = count === 1 ? target.dataset.labelOne : target.dataset.labelMany;

	target.textContent = (template ?? '').replace(':count', String(count));
}

function sync(): void {
	const bar = document.querySelector<HTMLElement>('[data-bulk-bar]');

	if (!bar) {
		return;
	}

	const all = boxes();
	const count = all.filter((box) => box.checked).length;

	bar.hidden = count === 0;

	const output = bar.querySelector<HTMLElement>('[data-bulk-count]');

	if (output) {
		fill(output, count);
	}

	const master = document.querySelector<HTMLInputElement>('input[data-bulk-all]');

	if (master) {
		master.checked = count > 0 && count === all.length;
		master.indeterminate = count > 0 && count < all.length;
	}
}

function openDialog(name: string): void {
	const dialog = document.querySelector<HTMLDialogElement>(`dialog[data-bulk-dialog="${name}"]`);
	const picked = selected();

	if (!dialog || picked.length === 0) {
		return;
	}

	const question = dialog.querySelector<HTMLElement>('[data-bulk-question]');

	if (question) {
		fill(question, picked.length);
	}

	const children = dialog.querySelector<HTMLElement>('[data-bulk-children]');

	if (children) {
		children.hidden = !picked.some((box) => box.hasAttribute('data-has-children'));

		const checkbox = children.querySelector<HTMLInputElement>('input');

		if (checkbox) {
			checkbox.checked = false;
		}
	}

	dialog.showModal();
}

function onChange(event: Event): void {
	const target = event.target;

	if (!(target instanceof HTMLInputElement)) {
		return;
	}

	if (target.matches('[data-bulk-all]')) {
		boxes().forEach((box) => {
			box.checked = target.checked;
		});
		sync();

		return;
	}

	if (target.matches('[data-bulk-check]')) {
		sync();
	}
}

function onClick(event: Event): void {
	const target = event.target instanceof Element ? event.target : null;

	if (!target) {
		return;
	}

	if (target.closest('[data-bulk-clear]')) {
		boxes().forEach((box) => {
			box.checked = false;
		});
		sync();

		return;
	}

	const open = target.closest<HTMLElement>('[data-bulk-open]');

	if (open) {
		openDialog(open.dataset.bulkOpen ?? '');

		return;
	}

	if (target.closest('[data-bulk-close]')) {
		target.closest('dialog')?.close();
	}
}

export function install(): () => void {
	document.addEventListener('change', onChange);
	document.addEventListener('click', onClick);
	document.addEventListener('htmx:after:swap', onSwap);
	stripNotice();

	return () => {
		document.removeEventListener('change', onChange);
		document.removeEventListener('click', onClick);
		document.removeEventListener('htmx:after:swap', onSwap);
	};
}
