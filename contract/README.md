# Cross-implementation contract fixtures

These files are executable spec for the seams where one contract deliberately has two implementations — one in PHP, one in the panel's TypeScript. Both test suites consume the same fixture file, so a change here fails on whichever side has not been updated yet. **Changing a file here means touching both implementations.**

## `conditions.json` — When-condition semantics

Two implementations of one semantics: `src/Field/Condition.php` evaluates conditions against stored node content at read time, `panel/src/behaviors/when.ts` evaluates the identical conditions against live form state in the editor.

Each case carries both representations of the same user state: `stored` is the neutral-locale value as PHP sees it in content (omit the key for a missing field), `form` is the string the browser form yields for it. Both suites must agree on `active`.

Consumed by `tests/Unit/FieldConditionTest.php` and `panel/tests/contract/conditions.test.ts`.

Conditions are currently top-level only on both sides. When scoped conditions inside entries are designed, their semantics land here first.

## `form-names.json` — bracket form-name parsing

Two implementations of one semantics: PHP's `parse_str()` is the reference that parses the native urlencoded fallback, and `panel/src/lib/form-json.ts` re-encodes the same submitted pairs into the nested JSON body the editor's JSON save transport posts. Both must hand the server the identical tree, or the two transports would save different content.

Each case: `entries` is the submitted `[name, value]` pairs in order, `tree` the expected parse result. Only well-formed bracket names are covered — top-level names must not contain dots, spaces or brackets outside `[...]` groups, because `parse_str()` mangles those; the panel never generates such names.

Consumed by `tests/Unit/FormNameContractTest.php` and `panel/tests/contract/form-names.test.ts`.

## `form-leaf.json` — the element `[json]` form leaf

A producer/consumer pipe, not two evaluators: `panel/src/lib/host.ts` serializes an element's live state into one JSON string submitted under the field's `[json]` key, and `src/Panel/FormPatch.php` merges that leaf back into stored content. The leaf in the middle is the shared artifact.

Each case: `stored` entry + `leaf` (the decoded leaf object; `leafRaw` for a deliberately malformed raw string) → `patched` expected entry.

Consumed by `tests/Unit/PanelFormPatchTest.php`. The producer side joins when the deferred browser-mode `host.ts` tests are written (jsdom cannot run form-associated custom elements) — see `../memory/cosray/panel/frontend-test-suite-plan.md` in the workspace.
