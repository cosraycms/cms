// JS half of the shared link rule; the PHP half is
// tests/Unit/RichtextLinkTest.php consuming the same fixture file.

import { describe, expect, it } from 'vitest';
import cases from '../../../contract/link-urls.json';
import { safeHref } from '../../src/lib/link-url.js';

describe('link urls', () => {
	it.each(cases as { url: string; safe: boolean }[])('$url', ({ url, safe }) => {
		expect(safeHref(url)).toBe(safe);
	});
});
