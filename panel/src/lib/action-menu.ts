type Align = 'start' | 'end' | 'center';

export function placement(
	anchor: HTMLElement,
	menu: HTMLElement,
	align: Align = 'start',
	inside = false,
) {
	const trigger = anchor.getBoundingClientRect();
	const viewport = window.visualViewport;
	let top = viewport?.offsetTop ?? 0;
	let left = viewport?.offsetLeft ?? 0;
	let bottom = top + (viewport?.height ?? window.innerHeight);
	let right = left + (viewport?.width ?? window.innerWidth);

	for (let parent = anchor.parentElement; parent; parent = parent.parentElement) {
		const style = getComputedStyle(parent);
		const box = parent.getBoundingClientRect();
		if (/^(auto|scroll|hidden|clip)$/.test(style.overflowY)) {
			const edge = box.top + parent.clientTop;
			top = Math.max(top, edge);
			bottom = Math.min(bottom, edge + parent.clientHeight);
		}
		if (/^(auto|scroll|hidden|clip)$/.test(style.overflowX)) {
			const edge = box.left + parent.clientLeft;
			left = Math.max(left, edge);
			right = Math.min(right, edge + parent.clientWidth);
		}
	}

	const style = getComputedStyle(menu);
	const gap = Math.max(
		parseFloat(style.marginTop) || 0,
		parseFloat(style.marginBottom) || 0,
		parseFloat(style.paddingTop) || 0,
	);
	const height = menu.scrollHeight + menu.offsetHeight - menu.clientHeight;
	const above = Math.max(0, trigger.top - top - gap);
	const below = Math.max(0, bottom - trigger.bottom - gap);
	const up = height > below && above > below;
	const maxWidth = Math.max(0, right - left);
	const width = Math.min(menu.offsetWidth, maxWidth);
	const visible =
		trigger.bottom > top && trigger.top < bottom && trigger.right > left && trigger.left < right;

	// Inside a trigger with room for it: centred, the top edge at the
	// trigger's middle so the first choice sits under a centred mark, or
	// centred outright when the lower half is too short. A smaller
	// trigger gets the menu below or above it like any other.
	if (inside && width + 2 * gap <= trigger.width && height + 2 * gap <= trigger.height) {
		const middle = trigger.top + trigger.height / 2;
		const preferred =
			middle + height + gap <= trigger.bottom
				? middle
				: trigger.top + (trigger.height - height) / 2;
		const edge = Math.max(top + gap, Math.min(preferred, bottom - gap - height));

		return {
			up: false,
			maxWidth,
			maxHeight: Math.max(0, bottom - edge),
			top: edge,
			left: Math.max(left, Math.min(trigger.left + (trigger.width - width) / 2, right - width)),
			visible,
		};
	}

	const start =
		align === 'center'
			? trigger.left + (trigger.width - width) / 2
			: align === 'end'
				? trigger.right - width
				: trigger.left;

	return {
		up,
		maxWidth,
		maxHeight: up ? above : below,
		top: up ? trigger.top - gap - Math.min(height, above) : trigger.bottom + gap,
		left: Math.max(left, Math.min(start, right - width)),
		visible,
	};
}

const MENU = '[data-action-menu]';
let id = 0;
let uninstall: (() => void) | null = null;
let active: {
	menu: HTMLElement;
	trigger: HTMLButtonElement;
	opener: HTMLElement;
	frame: number;
} | null = null;
let pending: {
	trigger: HTMLButtonElement;
	opener: HTMLElement;
	focus: 'first' | 'last' | false;
} | null = null;

function surface(trigger: HTMLButtonElement): HTMLElement | null {
	const menu = document.getElementById(trigger.getAttribute('popovertarget') ?? '');
	return menu?.matches(MENU) ? menu : null;
}

function choices(menu: HTMLElement): HTMLElement[] {
	return Array.from(menu.querySelectorAll<HTMLElement>('button, a[href]')).filter(
		(item) =>
			!item.matches(':disabled, [aria-disabled="true"]') &&
			item.checkVisibility({ visibilityProperty: true }),
	);
}

function reveal(menu: HTMLElement, item: HTMLElement): void {
	const box = item.getBoundingClientRect();
	const top = menu.getBoundingClientRect().top + menu.clientTop;
	const bottom = top + menu.clientHeight;
	if (box.top < top) menu.scrollTop -= top - box.top;
	else if (box.bottom > bottom) menu.scrollTop += box.bottom - bottom;
}

function focusChoice(menu: HTMLElement, item?: HTMLElement): void {
	for (const choice of choices(menu)) choice.tabIndex = choice === item ? 0 : -1;
	(item ?? menu).focus({ preventScroll: true });
	if (item) reveal(menu, item);
}

function finish(menu: HTMLElement): void {
	if (active?.menu !== menu) return;
	active.trigger.setAttribute('aria-expanded', 'false');
	cancelAnimationFrame(active.frame);
	active = null;
}

export function closeMenu(menu: HTMLElement, restore = false): void {
	const opener = active?.menu === menu ? active.opener : null;
	if (menu.isConnected && menu.matches(':popover-open')) menu.hidePopover();
	finish(menu);
	if (
		restore &&
		opener?.isConnected &&
		!opener.matches(':disabled') &&
		opener.checkVisibility({ visibilityProperty: true })
	) {
		opener.focus({ preventScroll: true });
	}
}

export function openMenu(
	trigger: HTMLButtonElement,
	focus: 'first' | 'last' | false = 'first',
	opener: HTMLElement = trigger,
): void {
	const menu = surface(trigger);
	if (!menu || trigger.disabled) return;
	if (menu.matches(':popover-open')) {
		if (focus) focusChoice(menu, focus === 'last' ? choices(menu).at(-1) : choices(menu)[0]);
		return;
	}
	pending = { trigger, focus, opener };
	try {
		menu.showPopover();
	} finally {
		pending = null;
	}
}

function toggle(event: Event): void {
	const menu = event.target;
	if (!(menu instanceof HTMLElement) || !menu.matches(MENU)) return;
	if ((event as ToggleEvent).newState === 'closed') {
		finish(menu);
		return;
	}

	const trigger =
		pending?.trigger ??
		document.querySelector<HTMLButtonElement>(`button[popovertarget="${CSS.escape(menu.id)}"]`);
	if (!trigger) return;
	const focus = pending?.focus ?? false;
	trigger.id ||= `cms-menu-trigger-${++id}`;
	trigger.setAttribute('aria-haspopup', 'menu');
	trigger.setAttribute('aria-expanded', 'true');
	menu.setAttribute('role', 'menu');
	menu.setAttribute('aria-labelledby', trigger.id);
	menu.tabIndex = -1;
	for (const item of menu.querySelectorAll<HTMLElement>('button, a[href]')) {
		if (!['menuitemcheckbox', 'menuitemradio'].includes(item.getAttribute('role') ?? ''))
			item.setAttribute('role', 'menuitem');
		item.tabIndex = -1;
	}

	if (active && active.menu !== menu) closeMenu(active.menu);
	const state = { menu, trigger, opener: pending?.opener ?? trigger, frame: 0 };
	active = state;

	const position = (): void => {
		if (active !== state) return;
		if (
			!menu.isConnected ||
			!trigger.isConnected ||
			!menu.matches(':popover-open') ||
			!trigger.checkVisibility({ visibilityProperty: true, opacityProperty: true })
		) {
			closeMenu(menu);
			return;
		}
		const align = menu.dataset.align;
		const point = placement(
			trigger,
			menu,
			align === 'end' || align === 'center' ? align : 'start',
			trigger.hasAttribute('data-menu-inside'),
		);
		if (!point.visible) {
			closeMenu(menu);
			return;
		}
		let moved = false;
		for (const name of ['left', 'top', 'maxWidth', 'maxHeight'] as const) {
			const value = `${point[name]}px`;
			if (menu.style[name] !== value) {
				menu.style[name] = value;
				moved = true;
			}
		}
		const focused = document.activeElement;
		if (moved && focused instanceof HTMLElement && menu.contains(focused) && focused !== menu)
			reveal(menu, focused);
		state.frame = requestAnimationFrame(position);
	};

	// beforetoggle runs while the popover still has no layout box.
	queueMicrotask(() => {
		if (active !== state) return;
		if (!menu.matches(':popover-open')) {
			finish(menu);
			return;
		}
		position();
		const items = choices(menu);
		if (items[0]) items[0].tabIndex = 0;
		if (focus && active === state) focusChoice(menu, focus === 'last' ? items.at(-1) : items[0]);
	});
}

function click(event: MouseEvent): void {
	const target = event.target instanceof Element ? event.target : null;
	const trigger = target?.closest<HTMLButtonElement>('button[popovertarget]');
	const menu = trigger && surface(trigger);
	if (trigger && menu) {
		event.preventDefault();
		if (menu.matches(':popover-open')) closeMenu(menu);
		else openMenu(trigger, event.detail === 0 ? 'first' : false);
		return;
	}

	const item = target?.closest<HTMLElement>('button, a[href]');
	if (!active || !item || item.closest(MENU) !== active.menu) return;
	if (
		!active.trigger.isConnected ||
		!active.menu.isConnected ||
		item.matches(':disabled, [aria-disabled="true"]')
	) {
		event.preventDefault();
		event.stopImmediatePropagation();
		return;
	}
	closeMenu(active.menu, true);
}

function keydown(event: KeyboardEvent): void {
	const target = event.target instanceof Element ? event.target : null;
	const menu = target?.closest<HTMLElement>(MENU);
	if (menu && active?.menu === menu) {
		event.stopPropagation();
		if (event.metaKey || event.ctrlKey || event.altKey) return;
		const items = choices(menu);
		const index = items.indexOf(document.activeElement as HTMLElement);
		let next: HTMLElement | undefined;
		switch (event.key) {
			case 'ArrowDown':
				next = items[(index + 1) % items.length];
				break;
			case 'ArrowUp':
				next = index < 0 ? items.at(-1) : items[(index - 1 + items.length) % items.length];
				break;
			case 'Home':
				next = items[0];
				break;
			case 'End':
				next = items.at(-1);
				break;
			case 'Escape':
				event.preventDefault();
				closeMenu(menu, true);
				return;
			case 'Enter':
			case ' ':
				event.preventDefault();
				items[index]?.click();
				return;
			default:
				return;
		}
		event.preventDefault();
		focusChoice(menu, next);
		return;
	}

	const trigger = target?.closest<HTMLButtonElement>('button[popovertarget]');
	if (!trigger || !surface(trigger) || event.metaKey || event.ctrlKey || event.altKey) return;
	if (event.key === 'Escape' && active?.trigger === trigger) {
		event.preventDefault();
		event.stopPropagation();
		closeMenu(active.menu, true);
		return;
	}
	if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
		event.preventDefault();
		event.stopPropagation();
		openMenu(trigger, event.key === 'ArrowUp' ? 'last' : 'first');
	}
}

function focusout(event: FocusEvent): void {
	if (!active || !(event.target instanceof Node) || !active.menu.contains(event.target)) return;
	if (event.relatedTarget instanceof Node && !active.menu.contains(event.relatedTarget))
		closeMenu(active.menu);
}

export function install(): () => void {
	if (uninstall) return () => {};
	const dismiss = () => {
		if (active) closeMenu(active.menu);
	};
	window.addEventListener('pagehide', dismiss);
	document.addEventListener('beforetoggle', toggle, true);
	document.addEventListener('click', click, true);
	document.addEventListener('keydown', keydown, true);
	document.addEventListener('focusout', focusout);
	uninstall = () => {
		dismiss();
		window.removeEventListener('pagehide', dismiss);
		document.removeEventListener('beforetoggle', toggle, true);
		document.removeEventListener('click', click, true);
		document.removeEventListener('keydown', keydown, true);
		document.removeEventListener('focusout', focusout);
		uninstall = null;
	};
	return uninstall;
}
