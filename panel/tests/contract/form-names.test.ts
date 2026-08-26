// JS half of the shared form-name parsing semantics; the PHP half is
// tests/Unit/FormNameContractTest.php running parse_str() over the same
// fixture file.

import { describe, expect, it } from 'vitest';
import fixtures from '../../../contract/form-names.json';
import { nest } from '../../src/lib/form-json';

type ContractCase = {
	name: string;
	entries: Array<[string, string]>;
	tree: Record<string, unknown>;
};

describe('contract: form-name parsing', () => {
	for (const item of fixtures.cases as unknown as ContractCase[]) {
		it(item.name, () => {
			expect(nest(item.entries)).toEqual(item.tree);
		});
	}
});
