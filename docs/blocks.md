# Blocks

A `Blocks` field contains typed rows with a grid layout. Each type declares fields and renders its frontend markup; the panel supplies an editor for those fields. This guide describes the current storage and rendering boundary, not a fixed editor design.

## Writing a block type

```php
namespace App\Block;

use Cosray\Block\RenderContext;
use Cosray\Contract\Block;
use Cosray\Field\Text;
use Cosray\Field\Textarea;
use Cosray\Schema\Label;
use Cosray\Schema\Required;
use Cosray\Schema\Translate;
use Cosray\Value\Block as BlockValue;

#[Label('Quote')]
final class Quote implements Block
{
    #[Label('Quote'), Required, Translate]
    protected Textarea $text;

    #[Label('Source')]
    protected Text $source;

    public function render(BlockValue $block, RenderContext $ctx): string
    {
        return "<blockquote><p>{$block->text}</p><cite>{$block->source}</cite></blockquote>";
    }
}
```

Type properties are schema declarations, not hydrated fields. Read values from the `Value\Block` argument. Plain text values escape on string conversion; richtext and media values supply their own rendered output, while Iframe deliberately emits trusted embed markup. A type is responsible for escaping any raw values it adds to its own HTML. Returning an empty string omits that block's wrapper and grid cell.

Types are created once per render call per type through the autowiring creator, not as shared container services. They do not receive a hydrated containing node; [RenderContext](../src/Block/RenderContext.php) supplies the owner, field name, grid columns, arguments, locale resolution, and asset lookup.

### Attributes and registration

`#[Label]`, `#[Handle]`, `#[Icon]`, and `#[FieldOrder]` configure type metadata. `#[Labels]` restores visible field labels for a single-field block; the current editor otherwise hides that redundant label visually, retaining its accessible name. Required fields still validate even where the editor omits their required markers. These are presentation choices, not different storage rules.

A field's `#[Allows(Quote::class)]` makes the type available without separate registration. `Registrar::blockType(Quote::class)` adds it to the default offer list used by fields without `#[Allows]`. Blocks and Entries cannot currently nest typed repeaters. Embedded declarations and fieldsets are supported as static field schemas.

## Built-in block types

[Cosray\Block](../src/Block/) contains:

| Type | Content |
| --- | --- |
| `RichText` | Structured richtext |
| `Text` | Escaped text with line breaks |
| `Heading` | Text and a heading-level option |
| `Image` | Single image in a figure, optional caption, responsive image ladder |
| `Images` | Gallery with ratio/crop settings |
| `Video` | Video player |
| `Youtube` | Video ID and aspect-ratio metadata |
| `Iframe` | Trusted embed code, rendered raw |

Text and media fields support translation. The heading level, YouTube ID, and iframe code are neutral. Per-use image metadata falls back to the catalog; galleries expose captions to templates but do not render them in the default gallery markup.

### Gallery settings

Gallery `ratio` and `crop` live in the images field's neutral metadata. [Field\Image](../src/Field/Image.php) defines supported ratios. Auto ratio and crop off are defaults. Output carries `data-ratio`, `--ratio`, and `data-crop` when configured; templates can read `$block->images->ratio()` and `crop()`.

## Configuring the field

```php
use App\Block\Quote;
use Cosray\Field\Blocks;
use Cosray\Schema\Allows;
use Cosray\Schema\Columns;
use Cosray\Schema\Responsive;
use Cosray\Schema\Translate;

#[Columns(12, min: 2, responsive: Responsive::Stack)]
#[Allows(Quote::class, Cosray\Block\RichText::class)]
#[Translate]
protected Blocks $content;
```

`#[Columns]` opts into a grid; without it, the field is a one-column stack. It currently accepts 1–25 columns, a minimum span, and a responsive policy (`Stack`, `Preserve`, or `Custom`). The column count is the default for new content only: every value stores the grid its blocks were placed on, so changing `#[Columns]` later leaves existing nodes as they are. `#[Required]` requires content in the relevant list; [richtext tools](controls.md#richtext-tools) and [full-text selection](fulltext.md) propagate to supported sub-fields according to their own rules.

### Common choices

The add menu currently offers the first six allowed types, with a searchable catalog for the rest. `#[Common(A::class, B::class)]` or `$blocks->common(...)` selects an ordered subset, at most six allowed types. Repeated calls replace the selection; an empty list restores the default. This changes presentation, not the set of valid stored types.

### Translation

| Declaration | Stored list | Sub-fields |
| --- | --- | --- |
| None | One `zxx` list | Neutral |
| `#[Translate]` | One shared `zxx` list | Translate where the type declares `#[Translate]` |
| `#[Translate(TranslateMode::Asymmetric)]` | One list per locale | Neutral within each list |

Symmetric translation shares structure; asymmetric translation permits different rows and layouts. Switching existing content between these modes requires a manual migration: different per-locale lists cannot be merged automatically. An empty selected translation may show an inert [fallback preview](controls.md#content-language-and-fallback-previews), but merely displaying it does not copy its rows.

## Stored shape

```json
{
	"type": "Cosray\\Field\\Blocks",
	"columns": 12,
	"value": {
		"zxx": [
			{
				"uid": "k3v9p2mq7x1zd",
				"type": "Cosray\\Block\\Image",
				"layout": { "colspan": 6, "rowspan": 1, "col": 3, "row": 1 },
				"fields": {
					"image": {
						"type": "Cosray\\Field\\Image",
						"value": { "zxx": [{ "uid": "asset-uid" }] }
					}
				},
				"meta": { "class": { "zxx": "hero" } }
			}
		]
	}
}
```

`columns` is the grid the value was placed on, `#[Columns]` for a new value. Row UIDs identify rows across edits and reordering. `type` is an FQCN; rows no longer allowed by the field are dropped on save. Fields retain their ordinary envelopes. Row meta includes `class`, `id`, and `padding`, each a neutral map; empty row meta can be omitted. Field meta holds gap settings.

`colspan` counts columns and `rowspan` grid rows; `col` and `row` are the 1-based lines the block starts at. A block sits exactly there, cells left free stay empty, and blocks never overlap. Rows are stored in reading order, row by row and left to right, which is also the order they stack in on narrow screens. A position is optional on write: a row without one is placed below the placed rows, the rows flowing in order the way the browser's grid flow places spans, so imports may leave it out.

Readers clamp layouts to the stored grid: span into `[min, columns]`, rows into `[1, 6]`, the start column into `[1, columns - span + 1]`. Direct Store writes validate rather than clamp: out-of-range values, a position with only one of its lines, and overlapping blocks are rejected. Editor saves clamp before validation. See [Layout](../src/Block/Layout.php), [Field\Blocks](../src/Field/Blocks.php), and [FormPatch](../src/Panel/FormPatch.php).

### Splits

A row can instead be a split: a `uid`, a `layout`, optional `meta`, and `blocks`, with no `type` and no `fields`. Its blocks are ordinary rows laid out on the split's area — their `colspan` counts the split's columns (the field's own tracks), their `rowspan` the split's rows — and they have no position of their own: they follow each other on the area:

```json
{
	"uid": "p8d2kq4mz7wn1",
	"layout": { "colspan": 6, "rowspan": 2, "col": 1, "row": 2 },
	"blocks": [
		{
			"uid": "k3v9p2mq7x1zd",
			"type": "Cosray\\Block\\Image",
			"layout": { "colspan": 3, "rowspan": 2 },
			"fields": {}
		},
		{
			"uid": "r5t1wq8ne2xb4",
			"type": "Cosray\\Block\\RichText",
			"layout": { "colspan": 3, "rowspan": 2 },
			"fields": {}
		}
	]
}
```

The direction is not stored. Blocks side by side (each as tall as the split) make a columns split, stacked blocks (each as wide as the split) a rows split. A split holds at least two blocks, and its blocks are never splits themselves. Validation places the blocks the way the browser's grid flow does and rejects one that would land outside the split's area: a subgrid has no rows of its own to grow into. Readers clamp each block into its split's area; an editor save turns a split left with one block into that block, with the split's layout.

## Editor form names

```text
content[f][columns]
content[f][value][lo][i][uid]
content[f][value][lo][i][type]
content[f][value][lo][i][layout][colspan]
content[f][value][lo][i][layout][rowspan]
content[f][value][lo][i][layout][col]
content[f][value][lo][i][layout][row]
content[f][value][lo][i][fields][sub][value][subLocale]
content[f][value][lo][i][fields][sub][json]
content[f][value][lo][i][fields][sub][meta][key][subLocale]
content[f][value][lo][i][meta][key][zxx]
content[f][value][lo][i][blocks][j][uid]
content[f][value][lo][i][blocks][j][type]
content[f][value][lo][i][blocks][j][layout][colspan]
content[f][value][lo][i][blocks][j][fields][sub][value][subLocale]
content[f][value][lo][i][blocks][j][meta][key][zxx]
```

`lo` is `zxx` for shared lists or a real locale for asymmetric lists. `columns` is submitted once per field, not per locale. A split submits no `type` and its blocks under `[blocks][j]`, with the same names below them; a block of a split submits a position of `0`, which means none. Submission replaces the row list in order, matching surviving rows by UID — across splits, so a block moved into or out of one keeps its stored data — and patching sub-fields individually. See [editor transport](controls.md#save-transport) for JSON encoding and truncation protection and [block controls](controls.md#blocks) for live editing behavior.

## Rendering

`(string) $node->content` or `$node->content->render(...)` emits a container and each non-empty rendered block:

```html
<div
	class="cms-blocks"
	data-columns="12"
	data-responsive="stack"
	style="--columns: 12"
>
	<div
		class="cms-block hero"
		data-type="image"
		data-colspan="6"
		data-rowspan="1"
		data-col="3"
		data-row="1"
		style="--colspan: 6; --rowspan: 1; --col: 3; --row: 1"
	>
		…
	</div>
</div>
```

A split renders as a `{prefix}-block` with `data-split="columns"` or `data-split="rows"` in place of `data-type`, the same layout attributes, and its blocks inside it as ordinary `{prefix}-block` elements whose spans count the split's area and which carry no position:

```html
<div
	class="cms-block"
	data-split="columns"
	data-colspan="6"
	data-rowspan="2"
	data-col="1"
	data-row="2"
	style="--colspan: 6; --rowspan: 2; --col: 1; --row: 2"
>
	<div class="cms-block" data-type="image" data-colspan="3" …>…</div>
	<div class="cms-block" data-type="richtext" data-colspan="3" …>…</div>
</div>
```

The container is emitted even for an empty field; `data-columns` and `--columns` carry the stored grid. Blocks are emitted in reading order. A block whose type renders nothing leaves its cells empty. Data attributes support styles that cannot use inline custom properties. All generated attribute values are escaped, including class/id settings; the type's own output is not sanitized by the wrapper.

### Spacing

Editor choices are tokens (`none`, `s`, `m`, `l`, `xl`), not lengths. A default choice stores nothing. Field meta can set `gap` or separate `rowGap`/`columnGap`; row meta can set `padding`. They become `data-gap`, `data-row-gap`, `data-column-gap`, and `data-padding`. Per-axis gaps beat shared gaps.

The editor keeps usable control spacing; Layout preview shows configured spacing using the reference stylesheet, not site-specific styles. Templates read `gap()`, `rowGap()`, `columnGap()`, and each block's `padding()`, returning null for defaults.

### Render arguments

| Argument | Purpose |
| --- | --- |
| `prefix` | Generated class prefix, default `cms` |
| `tag` | Container tag, default `div` |
| `class` | Additional container class |
| `imageSizes` | Named rendition ladder, default `block-sm`, `block`, `block-lg` |
| `sizes` | Image sizes template; `{pct}` is the block's share of the field's columns, inside a split too, default `(min-width: 48rem) {pct}vw, 100vw` |
| `thumbSize` | Gallery rendition, default `block-thumb` |

`tag` and `prefix` accept plain names matching `/^[a-z][a-z0-9-]*$/i`. An image ladder with several sizes requires width-mode sizes; one size can use any mode and emits a plain `src`. Missing assets produce no media block; non-resizable assets keep their original URL.

### The value API

[Value\Blocks](../src/Value/Blocks.php) iterates [Value\Block](../src/Value/Block.php) rows and exposes `count()`, `first()`, `last()`, `get()`, grid settings, image helpers, and richtext excerpts. Rows expose their identity, type/handle, layout, sub-fields as values, metadata, and rendering. `unwrap()`/`json()` return `{columns, blocks: [...]}` with resolved sub-field values, not the storage envelopes above.

Iteration and `count()` stay on the top-level rows, so a split is one row there: `isSplit()` tells it apart, `split()` returns `columns` or `rows`, `blocks()` its blocks, and its `type` and `handle()` are null. `leaves()` yields every block in reading order with a split's blocks in its place; `image()`, `images()`, `hasImage()`, and `excerpt()` read through it. A split's `unwrap()`/`json()` entry carries `blocks` instead of `fields`.

### The reference stylesheet

Import the shipped [resources/blocks.css](../resources/blocks.css), or copy it when a different prefix or layout needs adaptation:

```css
@import "vendor/cosray/cms/resources/blocks.css";
```

It uses the `cms.blocks` cascade layer, so ordinary unlayered site CSS wins. A block with a position sits on its lines: `grid-column: var(--col) / span var(--colspan)`, and the same for rows. Below the stacking threshold every block takes the full width in document order, which is the reading order. A split is a subgrid on both axes, so its blocks share the field's tracks; a site with its own grid sheet needs the same rules, or a split's blocks fall back to normal flow inside it.

Override points:

- `--blocks-gap-s` through `--blocks-gap-xl` and matching `--blocks-padding-*` values define the site's spacing scale.
- `--blocks-gap` supplies the gap when the editor chose nothing; an explicit token wins, including `none`.
- Gap values should be context-independent lengths such as `rem` or `px`; font-relative values follow whatever font size the container has.
- `--blocks-column-gap` and `--blocks-row-gap` are resolved internal values used by the sheet, rather than primary theme inputs.
- Container queries and gallery rules can be replaced by site styles. `Stack` collapses the grid below the sheet's threshold; `Preserve` and `Custom` leave it to the site.

The stylesheet itself is the reference for selectors and defaults; its implementation is not duplicated here.

## Migration from the legacy shape

Migration `000000-000031` converts blocks in nodes, working copies, and history. It moves spans into `layout`, converts absolute `colstart` to relative `indent` (which `000000-000040` turns into positions), drops old width/column metadata, maps legacy type IDs to classes, moves values into sub-fields, and generates missing UIDs. Layouts are copied, not clamped, because the migration does not know each field's schema.

Apply the normal application migrations before saving legacy content. Review `blocks-migration-report.json`, especially unknown types, unresolved field types, legacy richtext, and dropped media items. Unknown rows are retained by the migration but may be dropped on the next editor save. Richtext without its format marker still needs migration `000000-000020`.

Fields relying on the old implicit twelve columns need `#[Columns(12)]`; otherwise they are one-column stacks. The migration and renderer details live in [db/migrations/update/](../db/migrations/update/) and the implementation links above.

### Positions

Migration `000000-000040` places the blocks of nodes, working copies, and both history tables where the old relative layout put them: each list flowed, a block after its indent in the first spot from the previous block where it fit. Every top-level block gets the `col` and `row` of that spot, its indent is dropped, including from the blocks of splits, and each field stores the grid it was laid out on, read from the node type's `#[Columns]`. Spans are clamped as the readers clamped them, so blocks keep the size they rendered with. A list that already holds a position is left as it is, so the migration can run again. Content of a type no registered node class has stays untouched and is counted in the migration's output; readers place its blocks in order.

Sites need to switch any own block CSS from the indent (`--indent`, `--reserved`) to the positions (`--col`, `--row`); the reference stylesheet already has.

### Plain text blocks

Migration `000000-000041` turns the `Text` blocks of nodes, working copies, and both history tables into `RichText` blocks, including those in splits and nested blocks fields. Lines separated by a blank line become paragraphs and single line breaks hard breaks; a text holding only whitespace becomes an empty value. A block keeps its UID, layout, and settings. Its output changes from bare text with `<br>` to paragraphs in `<p>`, and its wrapper's `data-type` from `text` to `richtext`, so site CSS aimed at either needs checking. The rewrite keeps change times and records no history; content without `Text` blocks is left as it is, so the migration can run again. The `Text` type itself stays available.
