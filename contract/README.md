# Cross-implementation fixtures

These fixtures describe behavior shared across PHP and TypeScript. They make disagreement visible without maintaining a second prose implementation. Changing the intended behavior means checking both consumers; adding a case does not necessarily require changing either implementation.

## Conditions

[conditions.json](conditions.json) compares stored neutral values with their form representation. [Field\Condition](../src/Field/Condition.php) and [when.ts](../panel/src/behaviors/when.ts) should agree on whether the field is active.

Consumed by [FieldConditionTest](../tests/Unit/FieldConditionTest.php) and [conditions.test.ts](../panel/tests/contract/conditions.test.ts). Editor conditions currently apply to top-level fields; scoped conditions would need corresponding cases if implemented.

## Form names

[form-names.json](form-names.json) defines submitted name/value pairs and the expected nested tree. PHP's `parse_str()` handles the urlencoded fallback; [form-json.ts](../panel/src/lib/form-json.ts) builds the equivalent JSON body. A disagreement could save different content through the two transports.

Only generated, well-formed bracket names are covered. Dots/spaces or stray brackets in top-level names are outside that boundary because PHP mangles them.

Consumed by [FormNameContractTest](../tests/Unit/FormNameContractTest.php) and [form-names.test.ts](../panel/tests/contract/form-names.test.ts).

## Element form leaf

[form-leaf.json](form-leaf.json) exercises the `[json]` leaf produced by [host.ts](../panel/src/lib/host.ts) and merged by [FormPatch](../src/Panel/FormPatch.php). Cases specify stored content, a decoded leaf (or deliberately malformed raw leaf), and the expected patch result.

[PanelFormPatchTest](../tests/Unit/PanelFormPatchTest.php) covers the PHP consumer. The real host producer still needs browser-mode coverage: jsdom cannot exercise form-associated custom elements. The fixture alone is not an end-to-end guarantee.
