# Richtext format

Cosray stores richtext as a structured document rather than HTML. The current format is `cosray-richtext`, version `1`, implemented by [Cosray\Richtext](../src/Richtext/). The panel adapts it to ProseMirror; storage is not the editor's private serialization format.

This reference describes existing content semantics. It does not rule out future vocabulary or editor changes. Changes to persisted meanings still need an explicit migration strategy, even before a public release.

## Envelope

The format and version keys sit beside `value` on the field object:

```json
{
	"type": "Cosray\\Field\\RichText",
	"format": "cosray-richtext",
	"version": 1,
	"value": {
		"en": {
			"type": "doc",
			"content": [
				{
					"type": "paragraph",
					"content": [{ "type": "text", "text": "Hello" }]
				}
			]
		}
	}
}
```

Values are keyed by content locale, or `zxx` when neutral. [Normalizer](../src/Richtext/Normalizer.php) converts an empty document to null in its locale entry, rather than retaining an empty paragraph document. The field envelope is separate from that normalization.

A missing format marker denotes legacy HTML, which current readers do not render. Migration `000000-000020` converts that content; a bare HTML string is not valid input to the structured writer.

## Node tree

A document is a tree of `{type, attrs?, text?, marks?, content?}` objects. Canonical key order is `type`, `attrs`, `text`, `marks`, `content`; attribute keys and marks are sorted. Default-valued attributes and empty attribute/mark collections are omitted, and adjacent text runs with equal marks are merged. This keeps history diffs small and normalization idempotent.

[Spec.php](../src/Richtext/Spec.php) defines the vocabulary, content constraints, and defaults used by the validator and normalizer:

| Type | Content | Attributes |
| --- | --- | --- |
| `doc` | block+ | — |
| `paragraph` | inline\* | `class`, `align` |
| `heading` | inline\* | `level` (1–6), `align` |
| `bulletList` | listItem+ | — |
| `orderedList` | listItem+ | `start` (integer ≥ 1, default 1) |
| `listItem` | paragraph block\* | — |
| `blockquote` | block+ | — |
| `codeBlock` | text\*, without marks | — |
| `horizontalRule` | Leaf | `class` |
| `hardBreak` | Inline leaf | — |
| `text` | Inline leaf | Text and optional marks |
| `image` | Inline leaf | Asset `uid`, optional per-use `meta` |

`align` is left, center, right, justify, or null. Paragraph classes are declared through `richtext.classes`; `default` is implicit. `horizontalRule.class` currently permits a free-form nullable value for legacy content. Images are inline in this version; an image alone in a paragraph has no separate stored block-image mode. Per-use alt/title metadata falls back to the asset catalog.

## Marks

Supported marks are `bold`, `italic`, `underline`, `strike`, `code`, `subscript`, `superscript`, `style`, and `link`. Subscript and superscript exclude each other.

### Links

A link has exactly one target kind:

```json
{ "type": "link", "attrs": { "href": "https://example.com" } }
{ "type": "link", "attrs": { "node": "node-uid" } }
{ "type": "link", "attrs": { "asset": "asset-uid" } }
```

Node and asset references resolve at render time, so their URLs can change without rewriting prose. Optional `target` records editorial intent; `class` is optional. `rel` is not stored: the renderer applies link policy to external hrefs.

The reference scanner reads `image.uid`, `link.node`, and `link.asset`. Adding another reference carrier requires updating scanning and serialization as well as editing/rendering.

### Text styles

The style mark stores a named class, not a font-size value:

```json
{ "type": "style", "attrs": { "class": "lead" } }
```

Declare classes through configuration:

```php
'richtext.classes' => ['intro' => 'Introduction'],
'richtext.styles' => ['lead' => 'Lead text'],
```

Without declared text styles, the editor offers none and the writer rejects style marks with undeclared classes. Toolbar configuration controls feature exposure, not stored semantics; hiding a tool should preserve existing content on round-trip. See [richtext tools](controls.md#richtext-tools).

## Validation and rendering

Writers reject unknown node/mark types, invalid attributes, and invalid nesting, including marks in code blocks. Readers tolerate unknown vocabulary: the renderer skips unknown node types, ignores unknown marks, and reports notices. Tolerance is degradation, not a guarantee that an older editor can round-trip a newer document without loss.

The renderer resolves asset/node references and escapes generated values. Full-text extraction reads selected text directly from the tree, not rendered HTML; link destinations and image metadata do not become search prose. See [full-text search](fulltext.md).

## Current boundaries and evolution

The format currently has semantic prose containers rather than arbitrary div/span nesting. Page composition lives in [Blocks](blocks.md); tables and generic layout containers are not currently implemented in richtext. This separation limits renderer, scanner, and migration complexity, but can be reconsidered for a concrete editing need.

Changing an omitted attribute's default changes existing documents' meaning. Renames, restructures, and default changes therefore need a version/migration decision. Additive vocabulary may not require rewriting old content, but still needs coordinated PHP and panel support and tests for old readers/editors. Useful starting points are [Spec](../src/Richtext/Spec.php), the panel [schema](../panel/src/elements/richtext/schema.js), and its [format adapter](../panel/src/elements/richtext/format.js).

## Legacy migration

Migration `000000-000020` parses legacy HTML through the transitional converter, normalizes structured documents, and reports unresolved links/images or lossy conversion. Review `richtext-migration-report.json`; legacy `cms-text-*` spans may require matching declarations in `richtext.styles`.

Run application migrations through `php run db:migrations --apply` for an authorized upgrade, not as a routine documentation or test step. See [legacy imports](media.md#legacy-richtext-imports) for converter use outside that migration.
