// JS half of the shared link rule; the PHP half is
// tests/Unit/RichtextLinkTest.php consuming the same fixture file.

import { describe, expect, it } from 'vitest';
import rels from '../../../contract/link-rels.json';
import cases from '../../../contract/link-urls.json';
import { RELS, safeHref } from '../../src/lib/link-url.js';

describe('link urls', () => {
	it.each(cases as { url: string; safe: boolean }[])('$url', ({ url, safe }) => {
		expect(safeHref(url)).toBe(safe);
	});

	it('offers the shared rel values', () => {
		expect(RELS).toEqual(rels);
	});
});
