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

`#[Columns]` opts into a grid; without it, the field is a one-column stack. It currently accepts 1–25 columns, a minimum span, and a responsive policy (`Stack`, `Preserve`, or `Custom`). `#[Required]` requires content in the relevant list; [richtext tools](controls.md#richtext-tools) and [full-text selection](fulltext.md) propagate to supported sub-fields according to their own rules.

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
	"value": {
		"zxx": [
			{
				"uid": "k3v9p2mq7x1zd",
				"type": "Cosray\\Block\\Image",
				"layout": { "colspan": 6, "rowspan": 1, "indent": 2 },
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

Row UIDs identify rows across edits and reordering. `type` is an FQCN; rows no longer allowed by the field are dropped on save. Fields retain their ordinary envelopes. Row meta includes `class`, `id`, and `padding`, each a neutral map; empty row meta can be omitted. Field meta holds gap settings.

`colspan` counts columns, `rowspan` grid rows, and `indent` columns left free before the block **relative to its position in the flow**, not an absolute starting column. One-column fields use `{colspan: 1, rowspan: 1, indent: 0}`.

Readers clamp layouts to the configured field: span into `[min, columns]`, rows into `[1, 6]`, indent into `[0, columns - span]`. Direct Store writes validate rather than clamp out-of-range imports; editor saves clamp before validation. See [Layout](../src/Block/Layout.php), [Field\Blocks](../src/Field/Blocks.php), and [FormPatch](../src/Panel/FormPatch.php).

## Editor form names

```text
content[f][value][lo][i][uid]
content[f][value][lo][i][type]
content[f][value][lo][i][layout][colspan]
content[f][value][lo][i][layout][rowspan]
content[f][value][lo][i][layout][indent]
content[f][value][lo][i][fields][sub][value][subLocale]
content[f][value][lo][i][fields][sub][json]
content[f][value][lo][i][fields][sub][meta][key][subLocale]
content[f][value][lo][i][meta][key][zxx]
```

`lo` is `zxx` for shared lists or a real locale for asymmetric lists. Submission replaces the row list in order, matching surviving rows by UID and patching sub-fields individually. See [editor transport](controls.md#save-transport) for JSON encoding and truncation protection and [block controls](controls.md#blocks) for live editing behavior.

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
		data-indent="2"
		data-reserved="8"
		style="--colspan: 6; --rowspan: 1; --indent: 2; --reserved: 8"
	>
		…
	</div>
</div>
```

The container is emitted even for an empty field. `reserved` is the derived sum of indent and span. Data attributes support styles that cannot use inline custom properties. All generated attribute values are escaped, including class/id settings; the type's own output is not sanitized by the wrapper.

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
| `sizes` | Image sizes template; `{pct}` is the block's grid share, default `(min-width: 48rem) {pct}vw, 100vw` |
| `thumbSize` | Gallery rendition, default `block-thumb` |

`tag` and `prefix` accept plain names matching `/^[a-z][a-z0-9-]*$/i`. An image ladder with several sizes requires width-mode sizes; one size can use any mode and emits a plain `src`. Missing assets produce no media block; non-resizable assets keep their original URL.

### The value API

[Value\Blocks](../src/Value/Blocks.php) iterates [Value\Block](../src/Value/Block.php) rows and exposes `count()`, `first()`, `last()`, `get()`, grid settings, image helpers, and richtext excerpts. Rows expose their identity, type/handle, layout, sub-fields as values, metadata, and rendering. `unwrap()`/`json()` return `{columns, blocks: [...]}` with resolved sub-field values, not the storage envelopes above.

### The reference stylesheet

Import the shipped [resources/blocks.css](../resources/blocks.css), or copy it when a different prefix or layout needs adaptation:

```css
@import "vendor/cosray/cms/resources/blocks.css";
```

It uses the `cms.blocks` cascade layer, so ordinary unlayered site CSS wins. A block reserves indent plus span columns; its margin pushes the content past the indent within that area. This lets a block wrap naturally when it no longer fits beside its neighbors.

Override points:

- `--blocks-gap-s` through `--blocks-gap-xl` and matching `--blocks-padding-*` values define the site's spacing scale.
- `--blocks-gap` supplies the gap when the editor chose nothing; an explicit token wins, including `none`.
- Gap values need context-independent lengths such as `rem` or `px`: grid and indent math resolve the same length against different boxes. Percentages or font-relative values can disagree.
- `--blocks-column-gap` and `--blocks-row-gap` are resolved internal values used by the sheet, rather than primary theme inputs.
- Container queries and gallery rules can be replaced by site styles. `Stack` collapses the grid below the sheet's threshold; `Preserve` and `Custom` leave it to the site.

The stylesheet itself is the reference for selectors and defaults; its implementation is not duplicated here.

## Migration from the legacy shape

Migration `000000-000031` converts blocks in nodes, working copies, and history. It moves spans into `layout`, converts absolute `colstart` to relative `indent`, drops old width/column metadata, maps legacy type IDs to classes, moves values into sub-fields, and generates missing UIDs. Layouts are copied, not clamped, because the migration does not know each field's schema.

Apply the normal application migrations before saving legacy content. Review `blocks-migration-report.json`, especially unknown types, unresolved field types, legacy richtext, and dropped media items. Unknown rows are retained by the migration but may be dropped on the next editor save. Richtext without its format marker still needs migration `000000-000020`.

Fields relying on the old implicit twelve columns need `#[Columns(12)]`; otherwise they are one-column stacks. The migration and renderer details live in [db/migrations/update/](../db/migrations/update/) and the implementation links above.
