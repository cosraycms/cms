/**
 * Selection bookkeeping for the gallery drawer: the selected index has
 * to keep pointing at the same image while tiles are removed or dragged
 * into a new order around it.
 */

/** `length` is the item count after the removal. */
export function afterRemove(
	selected: number | null,
	removed: number,
	length: number,
): number | null {
	if (selected === null || length === 0) {
		return null;
	}

	if (removed < selected) {
		return selected - 1;
	}

	return Math.min(selected, length - 1);
}

export function afterMove(selected: number | null, from: number, to: number): number | null {
	if (selected === null || from === to) {
		return selected;
	}

	if (selected === from) {
		return to;
	}

	if (from < selected && to >= selected) {
		return selected - 1;
	}

	if (from > selected && to <= selected) {
		return selected + 1;
	}

	return selected;
}
