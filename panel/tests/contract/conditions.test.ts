// JS half of the shared When-condition semantics; the PHP half is
// tests/Unit/FieldConditionTest.php consuming the same fixture file.

import { describe, expect, it } from 'vitest';
import fixtures from '../../../contract/conditions.json';
import { active } from '../../src/behaviors/when';

type ContractCase = {
	name: string;
	condition: { field: string; op: string; value: unknown };
	stored?: unknown;
	form: string;
	active: boolean;
};

describe('contract: when-condition semantics', () => {
	for (const item of fixtures.cases as ContractCase[]) {
		it(item.name, () => {
			expect(active(item.condition, item.form)).toBe(item.active);
		});
	}
});
