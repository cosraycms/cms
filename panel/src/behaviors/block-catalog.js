/** @import { Insertion } from './repeater.js' */

import { cosray } from '../lib/bridge.js';
import { insertion, insert } from './repeater.js';

/** @typedef {'before' | 'after'} Position */

/**
 * @param {HTMLElement} host
 * @param {boolean} anchored
 * @param {(type: string, position?: Position) => void} choose
 */
function catalog(host, anchored, choose) {
	const search = /** @type {HTMLInputElement} */ (
		/** @type {HTMLInputElement | null} */ (host.querySelector('[data-block-search]'))
	);
	const status = /** @type {HTMLElement} */ (
		/** @type {HTMLElement | null} */ (host.querySelector('[data-block-results]'))
	);
	const choices = [
		.../** @type {NodeListOf<HTMLElement>} */ (host.querySelectorAll('[data-block-choice]')),
	];
	let visible = choices;
	/** @type {HTMLElement | undefined} */
	let active = choices[0];

	for (const choice of choices) {
		choice.setAttribute('role', anchored ? 'group' : 'button');
		/** @type {HTMLElement} */ (
			/** @type {HTMLElement | null} */ (choice.querySelector('.cms-block-actions'))
		).hidden = !anchored;
	}

	/**
	 * @param {HTMLElement | undefined} choice
	 * @param {boolean} [focus]
	 */
	function rove(choice, focus = false) {
		active = choice;
		for (const card of choices) {
			card.tabIndex = card === active ? 0 : -1;
			for (const action of /** @type {NodeListOf<HTMLButtonElement>} */ (
				card.querySelectorAll('[data-block-insert]')
			)) {
				action.tabIndex = anchored && card === active ? 0 : -1;
			}
		}
		if (focus && active) {
			active.focus({ preventScroll: true });
			active.scrollIntoView?.({ block: 'nearest', inline: 'nearest' });
		}
	}

	function filter() {
		const query = search.value.trim().toLocaleLowerCase(document.documentElement.lang || undefined);
		visible = choices.filter((choice) => {
			const text =
				`${/** @type {Element} */ (choice.querySelector('.select')).textContent} ${choice.dataset.handle}`.toLocaleLowerCase(
					document.documentElement.lang || undefined,
				);
			choice.hidden = !text.includes(query);
			return !choice.hidden;
		});
		rove(active && visible.includes(active) ? active : visible[0]);
		const { empty, one, count } = status.dataset;
		status.textContent =
			visible.length === 0
				? /** @type {string} */ (empty)
				: visible.length === 1
					? /** @type {string} */ (one)
					: /** @type {string} */ (count).replace(':count', String(visible.length));
	}

	search.addEventListener('input', filter);
	host.addEventListener('click', (event) => {
		const target = event.target instanceof Element ? event.target : null;
		const choice = /** @type {HTMLElement | null} */ (target?.closest('[data-block-choice]'));
		if (!choice || !visible.includes(choice)) return;
		const position = /** @type {HTMLElement | null} */ (target?.closest('[data-block-insert]'))
			?.dataset.blockInsert;
		if (position !== undefined) {
			if (anchored && (position === 'before' || position === 'after')) {
				choose(/** @type {string} */ (choice.dataset.blockChoice), position);
			}
			return;
		}
		if (!anchored) choose(/** @type {string} */ (choice.dataset.blockChoice));
	});
	host.addEventListener('focusin', (event) => {
		const choice =
			event.target instanceof Element
				? /** @type {HTMLElement | null} */ (event.target.closest('[data-block-choice]'))
				: null;
		if (choice && visible.includes(choice)) rove(choice);
	});
	host.addEventListener('keydown', (event) => {
		if (event.isComposing || event.altKey || event.ctrlKey || event.metaKey || event.shiftKey)
			return;
		if (event.target === search) {
			if (event.key === 'ArrowDown' || event.key === 'Enter') {
				event.preventDefault();
				rove(visible[0], true);
			}
			return;
		}
		if (!(event.target instanceof HTMLElement) || !visible.includes(event.target)) return;
		const index = visible.indexOf(event.target);
		/** @type {HTMLElement | undefined} */
		let next;
		switch (event.key) {
			case 'Enter':
			case ' ':
				event.preventDefault();
				if (!anchored) choose(/** @type {string} */ (event.target.dataset.blockChoice));
				return;
			case 'Home':
				next = visible[0];
				break;
			case 'End':
				next = visible.at(-1);
				break;
			case 'ArrowLeft':
				next = visible[Math.max(0, index - 1)];
				break;
			case 'ArrowRight':
				next = visible[Math.min(visible.length - 1, index + 1)];
				break;
			case 'ArrowUp':
			case 'ArrowDown': {
				const origin = event.target.getBoundingClientRect();
				const direction = event.key === 'ArrowDown' ? 1 : -1;
				const rows = visible
					.map((choice) => ({ choice, box: choice.getBoundingClientRect() }))
					.filter(({ box }) => (box.top - origin.top) * direction > 1);
				const distance = Math.min(...rows.map(({ box }) => Math.abs(box.top - origin.top)));
				next = rows
					.filter(({ box }) => Math.abs(Math.abs(box.top - origin.top) - distance) < 1)
					.sort(
						(a, b) =>
							Math.abs(a.box.left + a.box.width / 2 - origin.left - origin.width / 2) -
							Math.abs(b.box.left + b.box.width / 2 - origin.left - origin.width / 2),
					)[0]?.choice;
				break;
			}
			default:
				return;
		}
		event.preventDefault();
		if (next) rove(next, true);
	});
	filter();
}

/** @type {{ close(): void } | undefined} */
let opened;

/**
 * The owner's catalog for this insertion. Anchored to a row, the cards
 * offer before and after; `fixed` keeps them plain, the spot is settled.
 * A caller with an insertion of its own (a split) takes the choice.
 *
 * @param {Insertion} context
 * @param {boolean} [fixed]
 * @param {(type: string) => void} [choose]
 */
export function open(context, fixed = false, choose = (type) => insert(context, type)) {
	const template = /** @type {HTMLTemplateElement | null} */ (
		context.owner.querySelector(':scope > template[data-block-catalog]')
	);
	if (!template) return;
	opened?.close();
	const modal = cosray().modal.open(
		(host) => {
			let live = true;
			host.append(template.content.cloneNode(true));
			catalog(host, !fixed && context.at !== null, (type, position) => {
				if (!live) return;
				// Restore the menu opener before the repeater focuses the new row.
				modal.close();
				if (position) {
					insert({ ...context, at: context.at && { ...context.at, where: position } }, type);
				} else {
					choose(type);
				}
			});
			return () => {
				live = false;
				opened = undefined;
			};
		},
		{ hideClose: true, owner: context.at?.row ?? context.owner },
	);
	opened = modal;
}

/** @returns {() => void} */
export function install() {
	/**
	 * @param {MouseEvent} event
	 */
	function click(event) {
		const trigger =
			event.target instanceof Element ? event.target.closest('[data-block-catalog-open]') : null;
		const context = trigger && insertion(trigger);
		if (context) open(context);
	}
	document.addEventListener('click', click);
	return () => {
		document.removeEventListener('click', click);
		opened?.close();
	};
}
