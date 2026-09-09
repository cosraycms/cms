import type { Action } from 'svelte/action';

/**
 * Moves the node into `target` for as long as it lives; without a target
 * it stays where it was rendered. The node has to be the only root of
 * its block: Svelte tears a block down by walking from its first to its
 * last node, and a moved node breaks that walk for any sibling.
 */
export const portal: Action<HTMLElement, HTMLElement | undefined> = (node, target) => {
	if (target) {
		target.append(node);
	}

	return {
		destroy() {
			node.remove();
		},
	};
};
