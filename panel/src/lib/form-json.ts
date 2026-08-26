// Builds the nested tree PHP's parse_str() would build from the same
// submitted pairs, so the JSON save transport and the native urlencoded
// fallback produce identical form data on the server. The agreed cases
// live in contract/form-names.json; parse_str quirks outside them
// (dots or spaces in top-level names, stray brackets) are unsupported —
// the panel never generates such names.

type Tree = Record<string, unknown>;

function segments(name: string): string[] | null {
	const open = name.indexOf('[');

	if (open <= 0) {
		return open === -1 ? [name] : null;
	}

	const groups = name.slice(open);

	if (!/^(?:\[[^[\]]*\])+$/.test(groups)) {
		return null;
	}

	const parts = [name.slice(0, open)];

	for (const match of groups.matchAll(/\[([^[\]]*)\]/g)) {
		parts.push(match[1]);
	}

	return parts;
}

// parse_str appends [] entries at max(existing integer keys) + 1.
function nextIndex(node: Tree): number {
	let next = 0;

	for (const key of Object.keys(node)) {
		const parsed = Number(key);

		if (Number.isInteger(parsed) && parsed >= next && String(parsed) === key) {
			next = parsed + 1;
		}
	}

	return next;
}

export function nest(entries: Iterable<[string, string]>): Tree {
	const root: Tree = {};

	for (const [name, value] of entries) {
		// A malformed name becomes a literal top-level key instead of
		// being dropped; it still fails loudly server-side.
		const path = segments(name) ?? [name];
		let node = root;

		for (let i = 0; i < path.length - 1; i++) {
			const key = path[i] === '' && i > 0 ? String(nextIndex(node)) : path[i];
			const existing = node[key];

			if (typeof existing === 'object' && existing !== null) {
				node = existing as Tree;
			} else {
				const fresh: Tree = {};
				node[key] = fresh;
				node = fresh;
			}
		}

		const last = path[path.length - 1];
		node[last === '' && path.length > 1 ? String(nextIndex(node)) : last] = value;
	}

	return root;
}
