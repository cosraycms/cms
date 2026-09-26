/**
 * @param {HTMLElement} grid
 * @returns {() => void}
 */
function watch(grid) {
	let frame = 0;
	const observed = /** @type {Set<HTMLElement>} */ (new Set());
	const resize = new ResizeObserver(schedule);
	const mutations = new MutationObserver(schedule);

	function schedule() {
		if (!frame) frame = requestAnimationFrame(refresh);
	}

	function refresh() {
		frame = 0;
		const rows = Array.from(grid.children).filter(
			/** @returns {child is HTMLElement} */ (child) =>
				child instanceof HTMLElement && !child.hasAttribute('data-ghost'),
		);

		for (const row of observed) {
			if (row.parentElement !== grid) {
				resize.unobserve(row);
				observed.delete(row);
			}
		}

		const width = grid.clientWidth;
		const height = grid.clientHeight;
		const corners = rows.map((row) => {
			if (!observed.has(row)) {
				resize.observe(row);
				observed.add(row);
			}

			if (!width || !height || !row.offsetWidth || !row.offsetHeight) return '';

			// Offsets ignore Sortable's animation transforms. The positioned grid
			// is the offset parent; integer rounding can differ by one pixel.
			const left = Math.abs(row.offsetLeft) <= 1;
			const right = Math.abs(row.offsetLeft + row.offsetWidth - width) <= 1;
			const top = Math.abs(row.offsetTop) <= 1;
			const bottom = Math.abs(row.offsetTop + row.offsetHeight - height) <= 1;

			return [
				...(top && left ? ['top-left'] : []),
				...(top && right ? ['top-right'] : []),
				...(bottom && left ? ['bottom-left'] : []),
				...(bottom && right ? ['bottom-right'] : []),
			].join(' ');
		});

		rows.forEach((row, index) => {
			if (row.dataset.corners !== corners[index]) row.dataset.corners = corners[index];
		});
	}

	resize.observe(grid);
	mutations.observe(grid, {
		childList: true,
		subtree: true,
		attributes: true,
		attributeFilter: ['style', 'hidden'],
	});
	refresh();

	return () => {
		cancelAnimationFrame(frame);
		resize.disconnect();
		mutations.disconnect();
	};
}

/** @returns {() => void} */
export function install() {
	const grids = /** @type {Map<HTMLElement, () => void>} */ (new Map());

	function scan() {
		for (const [grid, dispose] of grids) {
			if (!grid.isConnected) {
				dispose();
				grids.delete(grid);
			}
		}

		/** @type {NodeListOf<HTMLElement>} */ (
			document.querySelectorAll('.cms-blocks-editor > .grid')
		).forEach((grid) => {
			if (!grids.has(grid)) grids.set(grid, watch(grid));
		});
	}

	/**
	 * @param {Event} event
	 */
	function changed(event) {
		if (event.target instanceof Element && event.target.closest('.cms-blocks-editor')) scan();
	}

	document.addEventListener('htmx:after:swap', scan);
	document.addEventListener('repeater:stamp', changed);
	document.addEventListener('change', changed);
	scan();

	return () => {
		document.removeEventListener('htmx:after:swap', scan);
		document.removeEventListener('repeater:stamp', changed);
		document.removeEventListener('change', changed);
		for (const dispose of grids.values()) dispose();
	};
}
