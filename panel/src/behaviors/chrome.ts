// Editor chrome: the content/settings pane tabs and the preview
// overlay's close button. The overlay anchor is only emptied, never
// removed — out-of-band swaps need the id to stay in the document.

function onClick(event: Event): void {
	const target = event.target;

	if (!(target instanceof Element)) {
		return;
	}

	const tab = target.closest<HTMLElement>('.tab[data-pane-tab]');

	if (tab) {
		const shell = tab.closest('.cms-node-shell');
		const pane = tab.dataset.paneTab ?? '';

		shell?.querySelectorAll('.tab[data-pane-tab]').forEach((other) => {
			other.classList.toggle('active', other === tab);
		});
		shell?.querySelectorAll<HTMLElement>('[data-pane]').forEach((section) => {
			section.hidden = section.dataset.pane !== pane;
		});

		return;
	}

	const close = target.closest('[data-overlay-close]');

	if (close) {
		const overlay = document.getElementById('editor-preview');

		if (overlay) {
			overlay.hidden = true;
			overlay.replaceChildren();
			overlay.removeAttribute('class');
		}
	}
}

export function install(): () => void {
	document.addEventListener('click', onClick);

	return () => {
		document.removeEventListener('click', onClick);
	};
}
