import { execFileSync } from 'node:child_process';
import { resolve } from 'node:path';
import { Translator } from '@celema/verba';

/** The panel catalog the server inlines into the page for a locale. */
export function catalog(locale = 'en'): Translator {
	return new Translator(
		JSON.parse(
			execFileSync('php', [resolve('../tests/Fixtures/Panel/catalog.php'), locale], {
				encoding: 'utf8',
			}),
		),
	);
}
