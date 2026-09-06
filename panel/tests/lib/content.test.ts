import { describe, expect, it } from 'vitest';
import { ensureLocales, ensureNeutral } from '../../src/lib/content';

describe('content locale scaffolds', () => {
	it('does not add missing locale keys to the source payload', () => {
		const source = { en: 'Hello' };

		expect(ensureLocales(source, '', [{ id: 'en' }, { id: 'de' }])).toEqual({
			en: 'Hello',
			de: '',
		});
		expect(source).toEqual({ en: 'Hello' });
	});

	it('does not add neutral content to the source payload', () => {
		const source = { en: 'Hello' };

		expect(ensureNeutral(source, '')).toEqual({ zxx: '', en: 'Hello' });
		expect(source).toEqual({ en: 'Hello' });
	});
});
