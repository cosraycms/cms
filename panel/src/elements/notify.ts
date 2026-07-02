import { getContext, setContext } from 'svelte';

// Instance-scoped dirty/dispatch channel inside a blocks element:
// BlocksElement provides the callback, block components report edits
// through it instead of importing editor stores.
const KEY = Symbol('cosray-blocks-notify');

export function provideNotify(fn: () => void): void {
	setContext(KEY, fn);
}

export function useNotify(): () => void {
	return getContext<(() => void) | undefined>(KEY) ?? (() => {});
}
