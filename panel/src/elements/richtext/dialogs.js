// The rich text editor's link and image dialogs. They open through the
// window.Cosray bridge, embed the asset library and a node search, and hand
// the choice back without touching the editor themselves.

/** @import { AssetInfo } from '../../types/data' */
/** @import { LibraryItem } from '../../lib/library-browser.js' */

import { cosray } from '../../lib/bridge.js';
import { icon } from '../../lib/icons.js';
import { browseLibrary } from '../../lib/library-browser.js';
import { __ } from '../../lib/locale.js';
import { panelBase } from '../../lib/runtime.js';

/**
 * Exactly one of href, node and asset carries the target, matching the
 * richtext `link` mark's attributes.
 *
 * @typedef {{ href?: string, node?: string, asset?: string }} LinkTarget
 */

/**
 * @typedef {object} NodeInfo
 * @property {string} uid
 * @property {string} title
 * @property {string} type
 * @property {string} typeLabel
 */

let ids = 0;

/**
 * @param {string} tag
 * @param {string} [className]
 * @param {string} [text]
 * @returns {HTMLElement}
 */
function create(tag, className, text) {
	const element = document.createElement(tag);

	if (className) element.className = className;
	if (text !== undefined) element.textContent = text;

	return element;
}

/**
 * @param {string} label
 * @param {'primary' | 'secondary'} variant
 * @param {() => void} onclick
 * @returns {HTMLButtonElement}
 */
function button(label, variant, onclick) {
	const element = document.createElement('button');
	element.type = 'button';
	element.className = `cms-button ${variant}`;
	element.textContent = label;
	element.addEventListener('click', onclick);

	return element;
}

/**
 * The frame every dialog shares: a titled header, the body, a footer.
 *
 * @param {HTMLElement} host
 * @param {string} title
 * @param {HTMLElement[]} body
 * @param {HTMLElement[]} footer
 */
function frame(host, title, body, footer) {
	const header = create('header', 'modal-header');
	const heading = create('h2', 'modal-title', title);
	heading.dataset.dialogTitle = '';
	header.append(heading);
	const main = create('div', 'modal-body');
	main.append(...body);
	const foot = create('footer', 'modal-footer');
	foot.append(...footer);
	host.append(header, main, foot);
}

/**
 * @param {string} path
 * @param {URLSearchParams} params
 * @returns {Promise<NodeInfo[]>}
 */
async function nodes(path, params) {
	const response = await fetch(`${panelBase()}${path}?${params.toString()}`, {
		credentials: 'same-origin',
		headers: { Accept: 'application/json', 'X-Requested-With': 'xmlhttprequest' },
	});
	const data = await response.json();

	return data.ok ? data.nodes : [];
}

/**
 * A node search for link targets: the current pick on top, results below
 * the search box as they arrive.
 *
 * @param {string} selected
 * @param {(node: NodeInfo) => void} pick
 * @returns {HTMLElement}
 */
function nodeSearch(selected, pick) {
	const root = create('div', 'cms-nodesearch');
	const current = create('div', 'cms-nodesearch-current');
	const input = document.createElement('input');
	input.className = 'cms-input';
	input.type = 'search';
	input.placeholder = __('node:search-page');
	const results = create('div');
	root.append(input, results);
	/** @type {ReturnType<typeof setTimeout> | undefined} */
	let timer;
	let request = 0;

	/** @param {NodeInfo} node */
	function showCurrent(node) {
		current.replaceChildren(
			create('span', 'cms-nodesearch-current-label', __('media:current')),
			create('span', 'cms-nodesearch-title', node.title || node.uid),
		);

		if (node.typeLabel) {
			current.append(create('span', 'cms-nodesearch-type', node.typeLabel));
		}

		root.prepend(current);
	}

	/** @param {NodeInfo[]} found */
	function list(found) {
		const items = create('ul', 'cms-nodesearch-results');

		for (const node of found) {
			const choice = document.createElement('button');
			choice.type = 'button';
			choice.className = 'cms-nodesearch-result';
			choice.append(create('span', 'cms-nodesearch-title', node.title || node.uid));

			if (node.typeLabel) {
				choice.append(' ', create('span', 'cms-nodesearch-type', node.typeLabel));
			}

			choice.addEventListener('click', () => {
				for (const other of items.querySelectorAll('.cms-nodesearch-result')) {
					other.classList.toggle('active', other === choice);
				}

				showCurrent(node);
				pick(node);
			});
			const item = create('li');
			item.append(choice);
			items.append(item);
		}

		return items;
	}

	async function search() {
		const term = input.value.trim();
		const mine = ++request;

		if (term === '') {
			results.replaceChildren();
			return;
		}

		results.replaceChildren(create('div', 'cms-nodesearch-empty', __('common:loading')));

		try {
			const found = await nodes('reference/nodes', new URLSearchParams({ q: term }));

			if (mine !== request) return;

			results.replaceChildren(
				found.length > 0
					? list(found)
					: create('div', 'cms-nodesearch-empty', __('search:no-results')),
			);
		} catch {
			if (mine === request) {
				results.replaceChildren(create('div', 'cms-nodesearch-empty', __('search:failed')));
			}
		}
	}

	input.addEventListener('input', () => {
		clearTimeout(timer);
		timer = setTimeout(() => void search(), 200);
	});

	if (selected !== '') {
		void nodes('reference/labels', new URLSearchParams({ uids: selected }))
			.then((found) =>
				showCurrent(found[0] ?? { uid: selected, title: selected, type: '', typeLabel: '' }),
			)
			.catch(() => {});
	}

	return root;
}

/**
 * Asks for a link target: a typed URL, a node, or an asset. Editing a link
 * opens on the tab matching its kind; an asset link on the files tab, which
 * browses every kind.
 *
 * @param {object} options
 * @param {HTMLElement} options.owner
 * @param {string} options.href
 * @param {string} options.node
 * @param {string} options.asset
 * @param {boolean} options.blank
 * @param {(target: LinkTarget, blank: boolean) => void} options.add
 */
export function openLinkDialog({ owner, href, node, asset, blank, add }) {
	const id = `cms-link-dialog-${++ids}`;
	/** @type {{ id: 'manually' | 'page' | 'images' | 'files', icon: string, label: string }[]} */
	const tabs = [
		{ id: 'manually', icon: 'link-45deg', label: __('link:manual') },
		{ id: 'page', icon: 'paragraph', label: __('node:page') },
		{ id: 'images', icon: 'image', label: __('media:images') },
		{ id: 'files', icon: 'file-earmark-richtext', label: __('media:files-documents') },
	];
	let current = node !== '' ? 'page' : asset !== '' ? 'files' : 'manually';
	let url = href;
	let pickedNode = node;
	let pickedAsset = asset;
	/** @type {(() => void) | null} */
	let stopBrowsing = null;

	const handle = cosray().modal.open(
		(host) => {
			const tablist = create('div', 'cms-tabs');
			tablist.setAttribute('role', 'tablist');
			tablist.setAttribute('aria-label', __('common:tabs'));
			const panel = create('div', 'files cms-modal-link-files');
			panel.setAttribute('role', 'tabpanel');
			panel.id = `${id}-panel`;
			const target = document.createElement('input');
			target.type = 'checkbox';
			target.id = `${id}-target`;
			target.className = 'cms-checkbox';
			target.checked = blank;
			const confirm = button(__('link:add'), 'primary', () => {
				/** @type {LinkTarget | null} */
				const chosen =
					current === 'page'
						? pickedNode !== ''
							? { node: pickedNode }
							: null
						: current === 'images' || current === 'files'
							? pickedAsset !== ''
								? { asset: pickedAsset }
								: null
							: url !== ''
								? { href: url }
								: null;

				if (chosen) {
					handle.close();
					add(chosen, target.checked);
				}
			});

			function refresh() {
				confirm.disabled =
					current === 'page'
						? pickedNode === ''
						: current === 'images' || current === 'files'
							? pickedAsset === ''
							: url === '';
			}

			function showPanel() {
				stopBrowsing?.();
				stopBrowsing = null;
				panel.setAttribute('aria-labelledby', `${id}-tab-${current}`);

				if (current === 'page') {
					panel.replaceChildren(
						nodeSearch(pickedNode, (info) => {
							pickedNode = info.uid;
							refresh();
						}),
					);
				} else if (current === 'images' || current === 'files') {
					const library = create('div');
					panel.replaceChildren(library);
					stopBrowsing = browseLibrary(library, {
						kind: current === 'images' ? 'image' : null,
						selected: pickedAsset || null,
						pick: (item) => {
							pickedAsset = item.uid;
							refresh();
						},
					});
				} else {
					const input = document.createElement('input');
					input.className = 'cms-input';
					input.type = 'text';
					input.dataset.dialogFocus = '';
					input.setAttribute('aria-label', __('link:manual'));
					input.value = url;
					input.addEventListener('input', () => {
						url = input.value;
						refresh();
					});
					const wrap = create('div', 'cms-modal-link-manual-input-wrap');
					wrap.append(input);
					const manual = create('div');
					manual.append(create('div', 'cms-modal-link-manual-hint', __('link:invalid-url')), wrap);
					panel.replaceChildren(manual);
				}

				refresh();
			}

			/** @param {string} next */
			function select(next) {
				current = next;

				for (const tab of tablist.querySelectorAll('[role="tab"]')) {
					const active = tab.id === `${id}-tab-${next}`;
					tab.setAttribute('aria-selected', String(active));
					tab.setAttribute('tabindex', active ? '0' : '-1');
				}

				showPanel();
			}

			for (const [index, entry] of tabs.entries()) {
				const tab = document.createElement('button');
				tab.type = 'button';
				tab.className = 'tab';
				tab.id = `${id}-tab-${entry.id}`;
				tab.setAttribute('role', 'tab');
				tab.setAttribute('aria-controls', `${id}-panel`);
				tab.setAttribute('aria-selected', String(entry.id === current));
				tab.tabIndex = entry.id === current ? 0 : -1;
				tab.innerHTML = icon(entry.icon);
				tab.append(create('span', undefined, entry.label));
				tab.addEventListener('click', () => select(entry.id));
				tab.addEventListener('keydown', (event) => {
					const next =
						event.key === 'ArrowRight' || event.key === 'ArrowDown'
							? (index + 1) % tabs.length
							: event.key === 'ArrowLeft' || event.key === 'ArrowUp'
								? (index - 1 + tabs.length) % tabs.length
								: event.key === 'Home'
									? 0
									: event.key === 'End'
										? tabs.length - 1
										: -1;

					if (next < 0) return;

					event.preventDefault();
					select(tabs[next].id);
					/** @type {HTMLElement | null} */ (tablist.children[next])?.focus();
				});
				tablist.append(tab);
			}

			const bodyWrap = create('div', 'cms-modal-link-body');
			bodyWrap.append(tablist, panel);
			const label = document.createElement('label');
			label.htmlFor = target.id;
			label.className = 'cms-checkbox-label';
			label.textContent = __('link:open-new-window');
			const targetInput = create('div', 'cms-modal-link-target-input-wrap');
			targetInput.append(target);
			const targetLabel = create('div', 'cms-modal-link-target-label-wrap');
			targetLabel.append(label);
			const targetRow = create('div', 'cms-modal-link-target-row');
			targetRow.append(targetInput, targetLabel);
			const targetWrap = create('div', 'cms-modal-link-target-wrap');
			targetWrap.append(targetRow);

			frame(
				host,
				__('richtext:add-link'),
				[bodyWrap, targetWrap],
				[button(__('common:cancel'), 'secondary', () => handle.close()), confirm],
			);
			showPanel();

			return () => stopBrowsing?.();
		},
		{ owner },
	);
}

/**
 * Asks for an image: a fresh upload inserts at once, a library pick after
 * confirming. An upload finishing after the dialog closed inserts nothing.
 *
 * @param {object} options
 * @param {HTMLElement} options.owner
 * @param {(uid: string, info: AssetInfo) => void} options.add
 */
export function openImageDialog({ owner, add }) {
	/** @type {LibraryItem | null} */
	let selected = null;
	let disposed = false;
	/** @type {(() => void) | null} */
	let stopBrowsing = null;

	const handle = cosray().modal.open(
		(host) => {
			const file = document.createElement('input');
			file.type = 'file';
			file.accept = 'image/*';
			file.className = 'cms-modal-image-upload-input';
			const upload = button(__('image:upload'), 'primary', () => file.click());
			const confirm = button(__('image:insert'), 'primary', () => {
				if (selected) {
					const item = selected;
					handle.close();
					add(item.uid, item);
				}
			});
			confirm.disabled = true;

			file.addEventListener('change', async () => {
				const chosen = file.files?.[0];

				if (!chosen) return;

				file.disabled = true;
				upload.disabled = true;
				upload.textContent = __('upload:uploading');
				const result = await cosray().upload('image', chosen);

				if (disposed) return;

				file.disabled = false;
				upload.disabled = false;
				upload.textContent = __('image:upload');
				file.value = '';

				if (!result.ok || !result.uid) {
					cosray().toast.error(result.error ?? __('upload:failed'));

					return;
				}

				handle.close();
				add(result.uid, {
					filename: result.filename ?? '',
					url: result.url ?? '',
					thumbUrl: result.thumbUrl,
					kind: 'image',
					mime: result.mime,
					width: result.width,
					height: result.height,
				});
			});

			const row = create('div', 'cms-modal-image-upload');
			row.append(
				file,
				upload,
				create('span', 'cms-modal-image-upload-hint', __('upload:or-from-library')),
			);
			const library = create('div', 'cms-modal-image-library');
			const body = create('div', 'cms-modal-image-body');
			body.append(row, library);
			frame(
				host,
				__('image:insert'),
				[body],
				[button(__('common:cancel'), 'secondary', () => handle.close()), confirm],
			);
			stopBrowsing = browseLibrary(library, {
				kind: 'image',
				pick: (item) => {
					selected = item;
					confirm.disabled = false;
				},
			});

			return () => {
				disposed = true;
				stopBrowsing?.();
			};
		},
		{ owner },
	);
}
