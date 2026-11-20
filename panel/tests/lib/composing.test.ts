import { describe, expect, it } from 'vitest';
import { composing } from '../../src/lib/composing';

describe('composing', () => {
	it('counts the key that ends a composition in Safari as part of it', () => {
		// Safari: isComposing false on the confirming Enter, keyCode 229.
		const safari = new KeyboardEvent('keydown', { key: 'Enter', keyCode: 229 });
		const plain = new KeyboardEvent('keydown', { key: 'Enter', keyCode: 13 });
		const composed = new KeyboardEvent('keydown', { key: 'Enter', isComposing: true });

		expect(composing(safari)).toBe(true);
		expect(composing(composed)).toBe(true);
		expect(composing(plain)).toBe(false);
	});
});
