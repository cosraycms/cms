# Blocks

A `Blocks` field is a list of typed rows with a grid layout: the editor stacks or places them, the frontend renders each row through its block type and wraps it in the layout contract below. A block type is a class with fields — exactly like an entry type — plus a `render()`.

Blocks and [entries](controls.md#entries) share the same row machinery; the difference is that a block carries a layout and knows how to render itself on the site.

## Writing a block type

A block type implements `Cosray\Contract\Block`. Its typed properties are schema declarations and are never assigned; `render()` reads the values off the `Value\Block` it is handed.

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

`$block->text` is the sub-field's `Value` object; interpolating it calls `__toString()`, which escapes for every field except `Iframe`. Use `->unwrap()` for the raw value. Returning `''` renders **no element at all** — that is how a block whose asset disappeared leaves no grid cell.

Instances are created once per render call per type, through the autowiring creator with the same request-scoped services an embedded class receives. A block type is never a container service (registering it as one is an error) and never receives a node.

### Class-level attributes

| Attribute | Effect |
| --- | --- |
| `#[Label('Quote')]` | the label in the block picker and the row's header strip |
| `#[Handle('quote')]` | the `data-type` value; derived kebab-case from the class name when absent |
| `#[FieldOrder('text', 'source')]` | the order the sub-fields render in |

Fieldsets come from `#[Fieldset]` on embedded properties, as on entry types.

### Sub-fields

Every field class works inside a block type, with two rules:

- A block type must not contain an `Entries` or another `Blocks` field. Nested typed repeaters are rejected at boot. Entry types may not contain a `Blocks` field either.
- `#[Translate]` on a sub-field only applies in symmetric mode — see [translation](#translation) below.

### Registering

A block type has to be reachable before a field can offer it:

```php
// In a plugin's register():
$cms->blockType(App\Block\Quote::class);
```

`Registrar::blockType()` adds the class to the **default offer list** — what a `Blocks` field without `#[Allows]` offers, on top of the eight built-ins. A field that lists its types with `#[Allows(Quote::class)]` needs no registration at all; `#[Allows]` takes class names, not ids.

## Built-in block types

`Cosray\Block\*`, one field each:

| Class | Handle | Field | Renders |
| --- | --- | --- | --- |
| `RichText` | `richtext` | `text: RichText`, required | the rendered richtext document |
| `Text` | `text` | `text: Textarea`, required | escaped, with `nl2br()` line breaks |
| `Heading` | `heading` | `text: Text`, required; `level: Option` `'1'`–`'6'`, default `'2'` | `<hN>` with the escaped text |
| `Image` | `image` | `image: Image`, one item, required | an `<img>` with a `srcset` ladder and a `sizes` attribute from the block's grid share |
| `Images` | `images` | `images: Image`, required | one `{prefix}-blocks-images-image` per item inside a `{prefix}-blocks-images` wrapper |
| `Video` | `video` | `video: Video`, one item, required | the `<video>` element |
| `Youtube` | `youtube` | `video: Youtube`, required | the responsive embed; the aspect ratio lives in the field's meta |
| `Iframe` | `iframe` | `code: Iframe`, required | the stored embed code **raw** |

The iframe block is the one deliberate unescaped output in the CMS: an embed field holds trusted editor input and is useless escaped. Everything a block type emits itself is its own responsibility; everything Cosray generates around it is escaped.

`richtext`, `text` and `heading` translate their text, the media types translate their media field (shared files, translated `alt` and `title`); the YouTube id, the iframe code and the heading level are not translatable.

## Configuring the field

```php
use Cosray\Field\Blocks;
use Cosray\Schema\Allows;
use Cosray\Schema\Columns;
use Cosray\Schema\Label;
use Cosray\Schema\Responsive;
use Cosray\Schema\Tool;
use Cosray\Schema\Tools;
use Cosray\Schema\Translate;

final class ArticlePage
{
    #[Label('Content')]
    #[Columns(12, min: 2, responsive: Responsive::Stack)]
    #[Allows(Quote::class, Cosray\Block\RichText::class)]
    #[Tools(Tool::MINIMAL)]
    #[Translate]
    protected Blocks $content;
}
```

| Attribute | Meaning |
| --- | --- |
| `#[Columns(int $columns, int $min = 1, Responsive $responsive = Responsive::Stack)]` | turns the field into a grid of `$columns` columns; `$min` is the narrowest span a block may take. **Without the attribute the field is a stacked one-column list** with no layout controls. `$columns` is 1–25. |
| `#[Allows(A::class, B::class)]` | the offered block types. Optional — without it the field offers the default list. |
| `#[Translate]` / `#[Translate(TranslateMode::Asymmetric)]` | see [translation](#translation). |
| `#[Tools(...)]` | feeds every `RichText` sub-field inside the offered block types that does not declare its own `#[Tools]`. |
| `#[Required]` | at least one block in the (default locale's) list. |

`Responsive` is `Stack`, `Preserve` or `Custom` and reaches the frontend as `data-responsive`; the [reference stylesheet](#the-reference-stylesheet) acts on `stack` only.

There is no schema attribute for a CSS class on the container — the `class` render argument does that, and the per-block `class` comes from the block's own settings dialog.

### Translation

| Declaration | Stored | Sub-fields |
| --- | --- | --- |
| none | one `zxx` list | all neutral |
| `#[Translate]` (symmetric) | one shared `zxx` list | each sub-field translates if the **block type** declares `#[Translate]` on it |
| `#[Translate(TranslateMode::Asymmetric)]` | one list per locale | all neutral — the list itself already translates the block |

Symmetric is the mode to reach for when the locales share a layout and only the text differs; asymmetric when a locale needs its own blocks in its own order. Switching a stored field between the modes later is a manual content migration: per-locale lists with different structures cannot be merged automatically.

## Stored shape

```jsonc
"content": {
    "type": "Cosray\\Field\\Blocks",
    "value": {
        // symmetric or untranslated: one shared list under the neutral locale;
        // asymmetric: one list per locale, {"de": [...], "en": [...]}
        "zxx": [
            {
                "uid": "k3v9p2mq7x1zd",
                "type": "Cosray\\Block\\Image",
                "layout": { "span": 6, "rows": 1, "indent": 0 },
                "fields": {
                    "image": {
                        "type": "Cosray\\Field\\Image",
                        "value": { "zxx": [{ "uid": "…", "meta": { "alt": { "de": "…" } } }] }
                    }
                },
                "meta": { "class": { "zxx": "wide" } }
            }
        ]
    },
    "meta": {}
}
```

- `uid` is a 13-character lowercase word-safe id, as on entry rows; the client fills it when a block is stamped, the server backfills a missing one.
- `type` is the block type's FQCN. Rows of a type the field no longer allows are shown as unknown and dropped on the next save.
- `layout` is always present and normalized. `span` counts columns, `rows` counts grid rows, `indent` counts the columns left free before the block (0 = none). The indent is relative to where the block falls in the flow, not an absolute column, so a block placed beside a neighbour is indented from that neighbour. For a one-column field the layout is `{1, 1, 0}`.
- `fields` holds the block type's fields in the ordinary field envelope, so every sub-field carries its own `type`, `value` locale map and optional `meta`.
- `meta` is the block's own settings, currently `class` and `id`, each a neutral-locale map. It is omitted when empty.

**Readers clamp** what they load: `span` into `[min, columns]`, `rows` into `[1, 6]`, `indent` into `[0, columns − span]`. Narrowing a field later, or importing out-of-range content, therefore never breaks a render — the block is simply placed inside the grid it has. A write **through the store** is not clamped but validated: an out-of-range layout is rejected, so a programmatic import fails loudly instead of persisting something the editor would silently rewrite. A save from the editor clamps before validating.

## Editor form names

The editor is server-rendered HTML — the same typed repeater entries use, one level deeper. Names mirror the stored structure, with `{lo}` the list's locale (`zxx` unless the field is asymmetric) and `i` the row index:

```text
content[f][value][{lo}][i][uid]                       hidden row identity
content[f][value][{lo}][i][type]                      hidden row type (FQCN)
content[f][value][{lo}][i][layout][span]              hidden, stepped by the toolbar
content[f][value][{lo}][i][layout][rows]
content[f][value][{lo}][i][layout][indent]
content[f][value][{lo}][i][fields][sub][value][lo]    primitive sub-field, per locale
content[f][value][{lo}][i][fields][sub][json]         element sub-field (cosray-host leaf)
content[f][value][{lo}][i][fields][sub][meta][k][lo]  sub-field meta dialog
content[f][value][{lo}][i][meta][class][zxx]          block settings dialog
content[f][value][{lo}][i][meta][id][zxx]
```

An asymmetric field renders one list per locale under the field-level locale tabs, so `{lo}` is a real locale and the sub-fields inside are neutral. A symmetric field renders a single `zxx` list whose rows carry the locale tabs: one strip per block switches every translated sub-field in it at once.

Saving replaces the row list wholesale — order is submission order, missing rows are deleted, rows of a disallowed type are dropped. Surviving rows are matched to their stored counterpart **by uid**, so unknown keys inside a row survive edits and reorders, and each sub-field is patched individually like a top-level field. Validation errors carry the row path and the summary jumps into the block.

## Rendering

`(string) $node->content` or `$node->content->render(...)` emits:

```html
<div
	class="cms-blocks"
	data-columns="12"
	data-responsive="stack"
	style="--columns: 12"
>
	<div
		class="cms-block hero"
		id="intro"
		data-type="richtext"
		data-span="8"
		data-rows="1"
		data-indent="2"
		data-reserved="10"
		style="--span: 8; --rows: 1; --indent: 2; --reserved: 10"
	>
		…
	</div>
</div>
```

- The container is `{prefix}-blocks` plus the `class` argument, with `data-columns`, `data-responsive` and `--columns`. It is emitted even when the field is empty.
- Each block is a `<div>` — `{prefix}-block` plus the block's `class` setting, the `id` setting, `data-type` (the type's handle) and the layout as both data attributes and custom properties, then the type's own output.
- `reserved` is `indent + span`, the columns the block takes out of its row. It is derived rather than stored, but carried like the rest so that CSS which cannot read the inline style still has it in one attribute instead of having to pair `data-indent` with `data-span`.
- The data attributes exist so a strict-CSP site can style through `[data-span='6']` selectors; the custom properties exist so the reference sheet stays twenty lines.

### Render arguments

| Argument | Default | Effect |
| --- | --- | --- |
| `prefix` | `cms` | prefixes every generated class name |
| `tag` | `div` | the container's tag |
| `class` | none | an extra class on the container |
| `imageSizes` | `['block-sm', 'block', 'block-lg']` | `media.sizes` names forming the image block's `srcset` ladder; a single entry emits a plain `src` and may use any mode, several entries must all use the `width` mode |
| `sizes` | `(min-width: 48rem) {pct}vw, 100vw` | the image block's `sizes` template; `{pct}` becomes the block's grid share in percent |
| `thumbSize` | `block-thumb` | the `media.sizes` name for gallery thumbs |

```php
<?= $node->content->render(tag: 'section', class: 'page-body', imageSizes: ['block', 'block-lg']) ?>
```

`tag` and `prefix` must be plain names (`/^[a-z][a-z0-9-]*$/i`) or the render throws. Every generated attribute value is escaped, the block settings `class` and `id` included; the layout values are integers. A block type's own output is not touched.

### The value API

`$node->content` is a `Cosray\Value\Blocks`:

| Member | Returns |
| --- | --- |
| iteration, `first()`, `last()`, `get(int $index)` | `Value\Block` rows |
| `count()`, `isset()` | how many blocks, and whether there are any |
| `columns()`, `responsive()` | the field's grid configuration |
| `image(int $index = 1)`, `hasImage(int $index = 1)` | the n-th image block's image |
| `images(bool $all = false)` | every image of the image and images blocks; with `$all` those of every locale's list |
| `excerpt(int $words = 30, string $allowedTags = '', int $index = 1)` | the n-th richtext block's excerpt |
| `render(...$args)`, `__toString()` | the markup above |
| `unwrap()`, `json()` | `{columns, blocks: [{uid, type, handle, layout, fields, meta}]}` |

One row is a `Cosray\Value\Block`:

| Member | Returns |
| --- | --- |
| `$block->fieldName` | the sub-field's `Value` object |
| `uid()`, `$block->type`, `handle()` | the row identity and its type (FQCN) and handle |
| `layout()` | a `Block\Layout` with `span`, `rows`, `indent` and `array()` |
| `meta(string $key, mixed $default = null)`, `styleClass()`, `elementId()` | the block settings |
| `render(...$args)`, `__toString()` | this block alone, wrapper included |

```php
foreach ($node->content as $block) {
    if ($block->handle() === 'quote') {
        echo $block->text->unwrap();
    }
}
```

### What a block type gets

`render()` receives the row and a `Cosray\Block\RenderContext`:

| Member | Returns |
| --- | --- |
| `$ctx->owner` | the node (or other owner) being rendered |
| `$ctx->fieldName` | the blocks field's name |
| `$ctx->columns` | the field's column count — the denominator for a block's width share |
| `$ctx->args` | the render arguments, unvalidated beyond `tag`/`prefix`/`class` |
| `tag()`, `prefix()`, `class()` | the validated container arguments |
| `effective(array $map)` | resolves a locale map along the locale fallback chain |
| `asset(string $uid)` | the catalog asset a media item references, or `null` |

## The reference stylesheet

`resources/blocks.css` ships with the package and implements the contract above:

```css
@import "vendor/cosray/cms/resources/blocks.css";
```

Copying it into the site's own CSS is equally fine — it is twenty lines and has no dependencies. A site rendering with a non-default `prefix` has to copy and rename.

```css
@layer cms.blocks {
	.cms-blocks {
		display: grid;
		grid-template-columns: repeat(var(--columns, 1), minmax(0, 1fr));
		gap: var(--blocks-gap, 2rem);
		container-type: inline-size;
	}

	.cms-block {
		min-width: 0;
		grid-column: span var(--reserved, 1);
		grid-row: span var(--rows, 1);
		margin-inline-start: calc(
			var(--indent, 0) * (100% + var(--blocks-gap, 2rem)) / var(--reserved, 1)
		);
	}

	@container (max-width: 42rem) {
		.cms-blocks[data-responsive="stack"] > .cms-block {
			grid-column: 1 / -1;
			grid-row: auto;
			margin-inline-start: 0;
		}
	}
}
```

A block spans `--reserved` columns — its indent plus its span — and a margin pushes its own box past the indent, so the indent stays in the flow instead of naming an absolute column. The percentage in that margin resolves against the block's own grid area, which is exactly `--reserved` columns wide, so one column is `(100% + gap) / reserved` and the sheet needs no measurement of the container. A block too wide for the columns still free in its row wraps onto the next one, the way any grid item does.

Everything sits in the `cms.blocks` cascade layer, so **unlayered site CSS wins** over it without needing a more specific selector. The intended override points are:

- `--blocks-gap` — the grid gap, set it on `.cms-blocks` or anywhere above it. It has to be a **context-independent length**, `rem` or `px`. The gap is resolved twice, once by the grid against the container and once by the indent margin against the block's own area, and the two agree only for a length that means the same in both places: a percentage does not (each resolves against its own box), and an `em` follows whatever font size the element it lands on has. Registering the property as a `<length>` would lift the restriction, but a registered property's initial value must be computationally independent and the `2rem` default is not, so the constraint stands.
- The container threshold — redeclare the `@container` block at the width the design wants. The container itself is `.cms-blocks` (`container-type: inline-size`), so the query measures the blocks area, not the viewport.

The three responsive policies:

| `data-responsive` | The sheet does | Use it for |
| --- | --- | --- |
| `stack` | collapses every block to `1 / -1` and `grid-row: auto` below the threshold | the common case: a 12-column page that becomes one column on a phone |
| `preserve` | nothing — the grid holds at every width | grids that stay meaningful when small, e.g. a two-column pair of logos |
| `custom` | nothing — bring your own rules keyed on `[data-responsive='custom']` | a layout whose small-screen behavior is per-block |

Styling by block type keys on `data-type`, which carries the type's handle:

```css
.cms-block[data-type="image"] img {
	display: block;
	width: 100%;
	height: auto;
}

.cms-block[data-type="quote"] cite {
	font-style: normal;
}
```

## Migration from the legacy shape

Migration `000000-000031` converts stored blocks to the typed-row shape in `nodes`, `drafts` and both history tables. **Run `php run db:migrations --apply` after upgrading** — the rebuilt field rejects the legacy shape, so an unmigrated node fails validation on its next save.

What it does per block:

- `colspan`, `rowspan` and `colstart` become `layout.span`, `layout.rows` and `layout.indent` (an offset, so `colstart: 3` is `indent: 2`). The legacy `colstart` named an absolute column and only ever placed a block that started its row; the offset is relative to the flow and renders such a block identically; `width` and the field's `columns`/`minCellWidth` meta are dropped.
- The block type ids become classes: the legacy `html` id and `richtext` map to `Cosray\Block\RichText`, `h1`–`h6` to one `Cosray\Block\Heading` with the level as its option, and the media, YouTube and iframe blocks to their type with the value moved into the type's field.
- The YouTube aspect ratio moves out of the block meta into the `Youtube` field's meta; `class` and `id` stay block meta; other meta keys are kept and reported.
- Blocks without a `uid` get one.
- Layouts are **copied, not clamped** — the field's schema is unknown at migration time, and readers clamp anyway.
- Blocks of an unknown type are left untouched and listed in the report.

The report is written to `blocks-migration-report.json` at the project root, with the counts `rows`, `updated`, `fields`, `blocks`, `types` (legacy id → count), `uidsGenerated`, `legacyRichtext`, `droppedMediaItems`, `droppedItems`, `metaKeys`, plus the lists `unknownTypes` (table, row, field, locale, index, type) and `unresolvedFieldTypes`. Three of them deserve a look after a run:

- `legacyRichtext` counts richtext blocks that carry no [richtext format envelope](richtext-format.md). They keep their bare value and render empty until migration `000000-000020` has covered them.
- `droppedMediaItems` counts items beyond the first in an `image` or `video` block — those types hold one item now.
- `unknownTypes` lists what was left behind; those rows still hold the legacy shape and will be dropped the next time the node is saved.

Two changes outside the blocks field come with the same release: a standalone `Iframe` field renders its code raw instead of escaped (it was unusable as an embed before), and a `Youtube` field validates its value as a video id (`[A-Za-z0-9_-]{1,64}`) on save and edits its aspect ratio through the field meta dialog.

Sites whose spans relied on the implicit 12 columns must add `#[Columns(12)]` to those fields — the column count is no longer stored per node, and without the attribute a field is a one-column stack.
