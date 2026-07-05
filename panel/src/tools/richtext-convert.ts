import { createInterface } from 'node:readline';

import { JSDOM } from 'jsdom';

import { pmToDoc } from '$shell/richtext/format';
import { parser } from '$shell/richtext/schema';

/**
 * Headless HTML-to-richtext converter for the one-shot content
 * migration: parses legacy HTML through the panel's own editor schema
 * (the schema most content was created with), so the migration output
 * is identical to what the editor itself would produce.
 *
 * Contract: NDJSON on stdin, one `{id, html}` unit per line; NDJSON on
 * stdout, `{id, doc}` (doc null for empty input) or `{id, error}`.
 */

const { document } = new JSDOM('').window;

function convert(html: string) {
	if (html.trim() === '') {
		return null;
	}

	const container = document.createElement('div');
	container.innerHTML = html;

	return pmToDoc(parser.parse(container as unknown as Node));
}

const lines = createInterface({ input: process.stdin, terminal: false });

lines.on('line', (line: string) => {
	if (line.trim() === '') {
		return;
	}

	let id = '';

	try {
		const unit = JSON.parse(line) as { id: string; html: string };
		id = unit.id;
		process.stdout.write(JSON.stringify({ id, doc: convert(unit.html ?? '') }) + '\n');
	} catch (error) {
		const message = error instanceof Error ? error.message : String(error);
		process.stdout.write(JSON.stringify({ id, error: message }) + '\n');
	}
});
