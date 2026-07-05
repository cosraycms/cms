# Cosray richtext format (v1)

Editor-agnostic storage format for richtext field and block values. Structurally modeled on ProseMirror's JSON (stable for 9 years, near-identity adapter), but owned, versioned, and documented by cosray. Editors (ProseMirror today, possibly Wordgard later) read and write it through adapters; the stored format never follows an editor.

Status: **v1, normative** — implemented by `Cosray\Richtext` (Validator, Normalizer, Renderer, Scanner).

## Envelope

Every richtext field value and every richtext block value is wrapped:

```json
{
	"format": "cosray-richtext",
	"version": 1,
	"value": { "de": { "type": "doc", "content": ["…"] } }
}
```

- `format` — `"cosray-richtext"` or `"html"` (legacy, during the migration window only). Self-describing for headless consumers.
- `version` — integer, applies to `cosray-richtext`. Bumped only on breaking restructures; each bump ships a system update migration (017-style rails).
- `value` — locale map. One format per field: a save converts all locales atomically. Non-translatable contexts use locale `zxx` (as image fields do).
- **Empty values**: an empty document is never stored as a doc with one empty paragraph. Canonical form drops empty locales from the map; a field empty in all locales stores `null` in place of the whole envelope.

## Node tree

A document is a tree of nodes. Fixed key order for byte-stable storage (history-table diffs, idempotent normalizers): `type`, `attrs`, `text`, `marks`, `content`. Attribute keys sorted; marks sorted by `type`.

```json
{ "type": "…", "attrs": {}, "content": [] }
{ "type": "text", "text": "…", "marks": [{ "type": "…", "attrs": {} }] }
```

Normalization: attributes equal to their spec default are **omitted**; empty `attrs`/`marks` are omitted; adjacent `text` nodes with identical mark sets are **merged** into one run. Defaults are frozen per version — changing a default is a breaking change.

## Nodes (v1)

| type | content | attrs |
| --- | --- | --- |
| `doc` | block+ | — |
| `paragraph` | inline\* | `class` (config-declared, default `"default"`), `align` |
| `heading` | inline\* | `level` (1–6, always stored), `align` |
| `bulletList` | listItem+ | — |
| `orderedList` | listItem+ | `start` (int ≥ 1, default 1) |
| `listItem` | paragraph block\* | — |
| `blockquote` | block+ | — |
| `codeBlock` | text\* (marks: none) | — |
| `horizontalRule` | (leaf) | `class` (string, default null) |
| `hardBreak` | (inline leaf) | — |
| `text` | (inline leaf) | `text` (string), `marks` |
| `image` | (inline leaf) | `uid` (asset uid), `meta` (object, default null) |

- `align` — `"left" | "center" | "right" | "justify" | null` (default null). Replaces the current free-form `textAlign` style string; migration normalizes anything else to null.
- `class` — declared in app config: `'richtext.classes' => ['classname' => 'Readable name']`; labels translatable later via `_('Readable name')`. `default` is implicit and always available. Writers reject undeclared classes. `horizontalRule.class` stays free-form nullable for now (produced by legacy content only) and can adopt the same mechanism once the editor exposes it.
- `image` — **new**; the current PM schema has none (legacy `<img>`s are silently stripped on save today). References an asset by uid; `meta` is an optional editorial override (`{alt, title}`) falling back to the asset catalog's meta at render time — same `{uid, meta?}` shape as image field items. **Inline-only in the format**: a "block image" is simply an image as the sole child of its own paragraph — an editorial arrangement, not a distinct node, so no display option exists at the storage level. The editor may offer "insert in own paragraph" as a convenience that creates exactly that. Block-level `figure` (caption) is a future additive node, not v1.

## Marks (v1)

| type | attrs | notes |
| --- | --- | --- |
| `bold` | — |  |
| `italic` | — |  |
| `underline` | — |  |
| `strike` | — |  |
| `code` | — |  |
| `subscript` | — | excludes `superscript` |
| `superscript` | — | excludes `subscript` |
| `style` | `class` (config-declared) | unusable until classes are declared; replaces `fontSize` |
| `link` | exactly one of `href` / `node` / `asset`; `target`, `class` (both default null) | see below |

### Links — three target kinds

```json
{ "type": "link", "attrs": { "href": "https://example.com" } }
{ "type": "link", "attrs": { "node": "<node-uid>" } }
{ "type": "link", "attrs": { "asset": "<asset-uid>" } }
```

- `node` — internal page link, resolved via `url_paths` at render time.
- `asset` — file/document link, resolved to the asset URL at render time.
- `rel` is **not stored** (diverges from the current PM schema, which bakes `noopener noreferrer nofollow` into content). Link policy is a render-time concern: external `href` links get the policy rel, internal refs get none. `target` stays — `_blank` is editorial intent.

`image.uid`, `link.node`, `link.asset` are the complete set of reference carriers: the Phase 2 reference scanner walks the tree and collects exactly these three.

### Text styles

`style` (named character styles, in the word-processor tradition) is the inline counterpart of `paragraph.class` and `link.class`: fixed shape (`{"type": "style", "attrs": {"class": "…"}}`, rendered as `<span class="…">`), value space declared in config:

```php
'richtext.styles' => ['cms-text-xl' => 'Groß']
```

With no declared classes (the default) the mark is unusable — no toolbar control, writers reject any value. There is no `fontSize` mark: font-size ladders are just a project's declared text styles.

## Validation and unknown types

- **Writers validate strictly**: saves and migrations must produce only spec vocabulary; unknown types, malformed attrs, or constraint violations (e.g. marks inside `codeBlock`) are rejected.
- **Readers tolerate**: the PHP renderer skips unknown node types / ignores unknown marks and logs, so an older renderer degrades instead of crashing.

## Anti-goals and the blocks boundary

- **No generic containers, ever.** No `div`/`span`/free-form container node with arbitrary class and nesting will be added. Every container is a named semantic type with declared content constraints — that is what keeps the renderer, the reference scanner, and future migrations predictable (and it is ProseMirror's and Wordgard's own model).
- **Richtext is flowing text; composition lives in blocks.** Styled boxes/callouts, tables, and anything column- or grid-shaped are **block types** in the blocks field, not richtext vocabulary. Tables are explicitly out of richtext scope.
- **Feature exposure is configuration, not format.** The vocabulary stays complete and stable; what a given field's editor offers (headings, alignment, …) is a per-field option. Disabling a feature hides its controls but keeps existing content intact on round-trip (soft-disable) — it never changes stored semantics. `heading` stays in the vocabulary and enabled by default; strict block-structured projects can disable it per field and use a (future) heading block type instead. Demand-driven styling features (the classic font-size request) are not vocabulary decisions either: the `style` mark is in the format, but its value space is empty until a project declares classes in config — the feature appears exactly when a customer demands it, never before.

## Evolution policy

- **Additive is free**: new node/mark types and new optional attrs (with defaults) extend the spec without a version bump and without migrating existing content. Example future additions: `figure`, `codeBlock.language`.
- **Breaking bumps the version**: renames, restructures, default changes. Each bump ships one system update migration; downstream apps apply it via the normal `php run migrations --apply` update flow. Design intent: v2 should be rare to never — the structure is deliberately boring (PM-shaped).

## Consumers

- **PHP renderer** — JSON → HTML for templates and the serializer; resolves uid refs (assets via catalog + `media.sizes`, nodes via `url_paths`); applies link policy.
- **PM adapter** (panel) — near-identity mapping to/from the live PM doc; refills omitted defaults, drops them again on save, maps `align` ↔ text-align style, renders `style` marks as class-carrying spans.
- **Serializer / headless** — emits the envelope verbatim; blif-ui et al. consume a cosray-documented format, not an editor's.
- **Reference scanner** (Phase 2) — collects `image.uid`, `link.node`, `link.asset`.

## Migration (one-shot, later phase)

HTML → v1 via ProseMirror's own parser (the schema most content was created with) run headless; round-trip diff report (JSON → HTML → normalized diff vs original) flags lossy nodes for review. Legacy `cms-text-*` spans become `style` marks; the report lists the class values each project must declare in `richtext.styles`. Envelope `format` distinguishes migrated from pending content during the window; after the sweep, `"html"` support is removed.

## Resolved (2026-07-05)

1. Empty value → `null`, never an empty-paragraph doc (see Envelope).
2. `image` → inline-only; block placement = sole image in its own paragraph, editor convenience allowed, no format-level option.
3. `paragraph.class` → config-declared list `'richtext.classes' => ['classname' => 'Readable name']`, i18n later via `_()`.
4. Styled boxes and tables → blocks layer, not richtext; no generic containers (see Anti-goals). `heading` stays in the vocabulary and on by default; per-field feature toggles decide exposure.
5. `fontSize` → removed from the vocabulary entirely; replaced by the config-declared `style` mark (`'richtext.styles' => ['classname' => 'Readable name']`), empty by default — the feature exists only where a project declares classes.
