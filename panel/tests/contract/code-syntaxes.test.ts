import { execFileSync } from 'node:child_process';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { expect, it } from 'vitest';
import { CODE_SYNTAXES, CODE_SYNTAX_ALIASES } from '../../src/elements/code/languages.js';

// PHP rejects a field syntax the editor lacks, so both must know the same
// keys and aliases.
it('accepts in PHP exactly the syntaxes the editor loads', () => {
	const php = JSON.parse(
		execFileSync(
			'php',
			[
				'-r',
				'require $argv[1]; echo json_encode(["keys" => Cosray\\Field\\CodeSyntaxes::KEYS, "aliases" => Cosray\\Field\\CodeSyntaxes::ALIASES]);',
				resolve(dirname(fileURLToPath(import.meta.url)), '../../../vendor/autoload.php'),
			],
			{ encoding: 'utf8' },
		),
	);

	expect([...CODE_SYNTAXES].sort()).toEqual([...php.keys].sort());
	expect(CODE_SYNTAX_ALIASES).toEqual(php.aliases);
});
