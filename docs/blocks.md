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
| `#[Icon('provider:id')]` | optional custom picker icon through the existing icon providers; unavailable icons use a generic fallback |
| `#[FieldOrder('text', 'source')]` | the order the sub-fields render in |
| `#[Labels]` | keeps the sub-field labels. A block type with a **single visible field** hides that field's label in the editor, since the block's own label already names it; declare this to bring it back. Types with two or more fields always label them. |

Fieldsets come from `#[Fieldset]` on embedded properties, as on entry types.

### Sub-fields

A block field can carry `#[Placeholder('...')]` — a lang key or literal shown inside the empty control — and the built-in single-field types do, so an empty block says what belongs into it. The attribute is available on every text-like field and on `Youtube`, in blocks and at the top level alike.

A block **marks none of its fields as required** in the editor: reaching for a block is what makes its content mandatory, so the mark would tell an editor nothing the block does not already say. `#[Required]` still validates — it is only the panel's view of the field that loses the marker, which also takes the required outline the rich text and media controls draw. Entry rows and top-level fields are unaffected.

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
| `Image` | `image` | `image: Image`, one item, required | a `<figure>` holding an `<img>` with a `srcset` ladder and a `sizes` attribute from the block's grid share, plus a `<figcaption>` when the image has a caption |
| `Images` | `images` | `images: Image`, required | one `{prefix}-blocks-images-image` per item inside a `{prefix}-blocks-images` wrapper, which carries the [gallery settings](#gallery-settings) as `data-ratio` (plus `--ratio`) and `data-crop` |
| `Video` | `video` | `video: Video`, one item, required | the `<video>` element |
| `Youtube` | `youtube` | `video: Youtube`, required | the responsive embed; the aspect ratio lives in the field's meta |
| `Iframe` | `iframe` | `code: Iframe`, required | the stored embed code **raw** |

The iframe block is the one deliberate unescaped output in the CMS: an embed field holds trusted editor input and is useless escaped. Everything a block type emits itself is its own responsibility; everything Cosray generates around it is escaped.

`richtext`, `text` and `heading` translate their text, the media types translate their media field (shared files, translated `alt` and `caption`); the YouTube id, the iframe code and the heading level are not translatable.

Per image, `$image->alt()`, `$image->caption()` and `$image->title()` read the per-use meta and fall back to the asset catalog. The image block renders the caption under the picture; the gallery renders none, the caption of a gallery image is for templates.

### Gallery settings

The images block's settings dialog holds the tiles' **aspect ratio** — auto, `1/1`, `4/3`, `3/2`, `16/9`, `3/4`, `2/3` — and a **crop** toggle: off fits each image into the ratio, on fills it. The editor's tiles preview both live. They are stored as the images field's meta, `{"ratio": {"zxx": "4/3"}, "crop": {"zxx": true}}`, and validated on save; auto and crop off are the site's defaults and are not stored. The rendered container carries them as `data-ratio="4/3" style="--ratio: 4/3"` and `data-crop`, which the [reference stylesheet](#the-reference-stylesheet) acts on; templates read them through `$block->images->ratio()` (`null` for auto) and `$block->images->crop()`.

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
| `#[Common(A::class, B::class)]` | the ordered short menu, up to six allowed types; see [common choices](#common-choices). |
| `#[Translate]` / `#[Translate(TranslateMode::Asymmetric)]` | see [translation](#translation). |
| `#[Tools(...)]` | feeds every `RichText` sub-field inside the offered block types that does not declare its own `#[Tools]`. Without it those sub-fields get `Tool::INLINE`, not the project's `richtext.tools`. |
| `#[Required]` | at least one block in the (default locale's) list. |

`Responsive` is `Stack`, `Preserve` or `Custom` and reaches the frontend as `data-responsive`; the [reference stylesheet](#the-reference-stylesheet) acts on `stack` only.

There is no schema attribute for a CSS class on the container — the `class` render argument does that, and the per-block `class` comes from the block's own settings dialog.

### Common choices

The `+` menu offers the first six allowed types in the order they are allowed; with one type it inserts at once, with none there is nothing to add. When more types remain, **More blocks…** opens a catalog of every allowed type, common ones included, searchable by label or handle.

Use `Cosray\Schema\Common` to choose and order the menu:

```php
use Cosray\Block;
use Cosray\Field\Blocks;
use Cosray\Schema\Allows;
use Cosray\Schema\Common;

#[Allows(Block\RichText::class, Block\Heading::class, Block\Image::class, Block\Video::class)]
#[Common(Block\RichText::class, Block\Image::class)]
protected Blocks $content;
```

The fluent form is `$blocks->common(Type::class, ...)`. Each call replaces the list, duplicates collapse, and an empty list restores the default. A class that is not a block fails at once. The list may hold at most six types, all of them allowed, and that is checked when the control is resolved, so `Common` and `Allows` may be declared in either order. The menu is presentation only: it does not change what the field allows, validates or stores.

### Translation

| Declaration | Stored | Sub-fields |
| --- | --- | --- |
| none | one `zxx` list | all neutral |
| `#[Translate]` (symmetric) | one shared `zxx` list | each sub-field translates if the **block type** declares `#[Translate]` on it |
| `#[Translate(TranslateMode::Asymmetric)]` | one list per locale | all neutral — the list itself already translates the block |

Symmetric is the mode to reach for when the locales share a layout and only the text differs; asymmetric when a locale needs its own blocks in its own order. Switching a stored field between the modes later is a manual content migration: per-locale lists with different structures cannot be merged automatically.

The node editor's one content-language selector switches the translated sub-fields of a symmetric list or the complete list of an asymmetric field. If the selected asymmetric list is empty, the editor follows the configured locale fallback chain and shows the first populated list as an inert, labelled preview. The selected list remains empty and keeps its own add controls; inserting a block creates it only there. Merely switching locale, focusing the add controls, or saving another field never copies the preview rows.

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
                "layout": { "colspan": 6, "rowspan": 1, "indent": 0 },
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
- `layout` is always present and normalized. `colspan` counts columns, `rowspan` counts grid rows, `indent` counts the columns left free before the block (0 = none). The indent is relative to where the block falls in the flow, not an absolute column, so a block placed beside a neighbour is indented from that neighbour. For a one-column field the layout is `{1, 1, 0}`.
- `fields` holds the block type's fields in the ordinary field envelope, so every sub-field carries its own `type`, `value` locale map and optional `meta`.
- `meta` is the block's own settings — `class`, `id` and `padding` — each a neutral-locale map. It is omitted when empty.
- The field's own `meta` holds its gap settings — `gap`, or `rowGap` and `columnGap` — as neutral-locale maps of spacing tokens; see [spacing](#spacing).

**Readers clamp** what they load: `colspan` into `[min, columns]`, `rowspan` into `[1, 6]`, `indent` into `[0, columns − colspan]`. Narrowing a field later, or importing out-of-range content, therefore never breaks a render — the block is simply placed inside the grid it has. A write **through the store** is not clamped but validated: an out-of-range layout is rejected, so a programmatic import fails loudly instead of persisting something the editor would silently rewrite. A save from the editor clamps before validating.

## Editor form names

The editor is server-rendered HTML — the same typed repeater entries use, one level deeper. Names mirror the stored structure, with `{lo}` the list's locale (`zxx` unless the field is asymmetric) and `i` the row index:

```text
content[f][value][{lo}][i][uid]                       hidden row identity
content[f][value][{lo}][i][type]                      hidden row type (FQCN)
content[f][value][{lo}][i][layout][colspan]           hidden, stepped by the toolbar
content[f][value][{lo}][i][layout][rowspan]
content[f][value][{lo}][i][layout][indent]
content[f][value][{lo}][i][fields][sub][value][lo]    primitive sub-field, per locale
content[f][value][{lo}][i][fields][sub][json]         element sub-field (cosray-host leaf)
content[f][value][{lo}][i][fields][sub][meta][k][lo]  sub-field meta dialog
content[f][value][{lo}][i][meta][class][zxx]          block settings dialog
content[f][value][{lo}][i][meta][id][zxx]
```

An asymmetric field renders one list per locale, so `{lo}` is a real locale and the sub-fields inside are neutral. A symmetric field renders a single `zxx` list. The node editor's sidebar owns one content-language selector: it switches the asymmetric list or every translated sub-field in the shared rows while neutral fields remain visible and editable. An empty asymmetric list may display a fallback list at the same time, but that preview remains under its source locale's existing names; it is inert and does not become part of the selected locale.

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
		data-colspan="8"
		data-rowspan="1"
		data-indent="2"
		data-reserved="10"
		style="--colspan: 8; --rowspan: 1; --indent: 2; --reserved: 10"
	>
		…
	</div>
</div>
```

- The container is `{prefix}-blocks` plus the `class` argument, with `data-columns`, `data-responsive` and `--columns`. It is emitted even when the field is empty.
- Each block is a `<div>` — `{prefix}-block` plus the block's `class` setting, the `id` setting, `data-type` (the type's handle) and the layout as both data attributes and custom properties, then the type's own output.
- `reserved` is `indent + colspan`, the columns the block takes out of its row. It is derived rather than stored, but carried like the rest so that CSS which cannot read the inline style still has it in one attribute instead of having to pair `data-indent` with `data-span`.
- The data attributes exist so a strict-CSP site can style through `[data-colspan='6']` selectors; the custom properties exist so the reference sheet needs no lookup table.
- When the editor chose spacing, the container carries `data-gap`, `data-row-gap` and `data-column-gap` and a block `data-padding`, each one of the tokens `none`, `s`, `m`, `l`, `xl`; nothing is emitted for the site's default. See [spacing](#spacing).

### Spacing

The blocks field's meta dialog sets the **gap** between its blocks, as one value or — on a grid, behind a toggle — as a row gap and a column gap of their own; a block's settings dialog sets its **padding**. Both choose from the tokens `none`, `s`, `m`, `l` and `xl`, behind a "Site default" choice that stores nothing, so existing content renders as before. The editor previews every choice on its canvas in its own scale.

On the site the tokens are attributes, never lengths: `data-gap`, `data-row-gap` and `data-column-gap` on the container, `data-padding` on the block. A per-axis gap beats the shared one. The [reference stylesheet](#the-reference-stylesheet) maps each token to a variable the site sets to its own scale — `--blocks-gap-s` … `--blocks-gap-xl`, `--blocks-padding-s` … `--blocks-padding-xl` — so the site decides what "large" means; `--blocks-gap` is the site's default, the gap a field renders with when the editor chose nothing, and a chosen token always wins over it, `none` included. Templates read the tokens through `$node->content->gap()`, `rowGap()` and `columnGap()` and a block's `padding()`, each `null` for the default.

### Render arguments

| Argument | Default | Effect |
| --- | --- | --- |
| `prefix` | `cms` | prefixes every generated class name |
| `tag` | `div` | the container's tag |
| `class` | none | an extra class on the container |
| `imageSizes` | `['block-sm', 'block', 'block-lg']` | `media.sizes` names forming the image block's `srcset` ladder; a single entry emits a plain `src` and may use any mode, several entries must all use the `width` mode |
| `sizes` | `(min-width: 48rem) {pct}vw, 100vw` | the image block's `sizes` template; `{pct}` becomes the block's grid share in percent |
| `thumbSize` | `block-thumb` | the `media.sizes` name for gallery tiles, a 480 px wide rendition by default; the tiles' shape comes from the [gallery settings](#gallery-settings), so the rendition should not crop |

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
| `gap()`, `rowGap()`, `columnGap()` | the [spacing](#spacing) tokens, `null` for the site's default |
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
| `layout()` | a `Block\Layout` with `colspan`, `rowspan`, `indent` and `array()` |
| `meta(string $key, mixed $default = null)`, `styleClass()`, `elementId()`, `padding()` | the block settings; the padding as a [spacing](#spacing) token or `null` |
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

Copying it into the site's own CSS is equally fine — it is short and has no dependencies. A site rendering with a non-default `prefix` has to copy and rename.

```css
@layer cms.blocks {
	/*
	 * `--blocks-column-gap` is the resolved column gap the grid and the
	 * indent math read, `--blocks-row-gap` the row gap. Both start from the
	 * site's default `--blocks-gap`; a shared `data-gap` sets both, a
	 * per-axis attribute only its own.
	 */
	.cms-blocks {
		--blocks-column-gap: var(--blocks-gap, var(--blocks-gap-m, 2rem));
		--blocks-row-gap: var(--blocks-gap, var(--blocks-gap-m, 2rem));

		display: grid;
		grid-template-columns: repeat(var(--columns, 1), minmax(0, 1fr));
		column-gap: var(--blocks-column-gap);
		row-gap: var(--blocks-row-gap);
		container-type: inline-size;
	}

	.cms-blocks[data-gap="none"] {
		--blocks-column-gap: 0px;
		--blocks-row-gap: 0px;
	}

	.cms-blocks[data-gap="s"] {
		--blocks-column-gap: var(--blocks-gap-s, 1rem);
		--blocks-row-gap: var(--blocks-gap-s, 1rem);
	}

	.cms-blocks[data-gap="m"] {
		--blocks-column-gap: var(--blocks-gap-m, 2rem);
		--blocks-row-gap: var(--blocks-gap-m, 2rem);
	}

	.cms-blocks[data-gap="l"] {
		--blocks-column-gap: var(--blocks-gap-l, 3rem);
		--blocks-row-gap: var(--blocks-gap-l, 3rem);
	}

	.cms-blocks[data-gap="xl"] {
		--blocks-column-gap: var(--blocks-gap-xl, 4.5rem);
		--blocks-row-gap: var(--blocks-gap-xl, 4.5rem);
	}

	/* A gap set per axis beats the shared one. */
	.cms-blocks[data-column-gap="none"] {
		--blocks-column-gap: 0px;
	}

	.cms-blocks[data-column-gap="s"] {
		--blocks-column-gap: var(--blocks-gap-s, 1rem);
	}

	.cms-blocks[data-column-gap="m"] {
		--blocks-column-gap: var(--blocks-gap-m, 2rem);
	}

	.cms-blocks[data-column-gap="l"] {
		--blocks-column-gap: var(--blocks-gap-l, 3rem);
	}

	.cms-blocks[data-column-gap="xl"] {
		--blocks-column-gap: var(--blocks-gap-xl, 4.5rem);
	}

	.cms-blocks[data-row-gap="none"] {
		--blocks-row-gap: 0px;
	}

	.cms-blocks[data-row-gap="s"] {
		--blocks-row-gap: var(--blocks-gap-s, 1rem);
	}

	.cms-blocks[data-row-gap="m"] {
		--blocks-row-gap: var(--blocks-gap-m, 2rem);
	}

	.cms-blocks[data-row-gap="l"] {
		--blocks-row-gap: var(--blocks-gap-l, 3rem);
	}

	.cms-blocks[data-row-gap="xl"] {
		--blocks-row-gap: var(--blocks-gap-xl, 4.5rem);
	}

	/*
	 * The indent is relative to the flow: a block reserves it along with
	 * its span (`--reserved` is the sum) and pushes its own box past it.
	 * The percentage resolves against the block's own grid area, which is
	 * `--reserved` columns wide, so one column is (100% + gap) / reserved.
	 * A block too wide for the columns left in its row wraps to the next.
	 */
	.cms-block {
		min-width: 0;
		grid-column: span var(--reserved, 1);
		grid-row: span var(--rowspan, 1);
		margin-inline-start: calc(
			var(--indent, 0) * (100% + var(--blocks-column-gap)) / var(--reserved, 1)
		);
	}

	.cms-block[data-padding="none"] {
		padding: 0;
	}

	.cms-block[data-padding="s"] {
		padding: var(--blocks-padding-s, 1rem);
	}

	.cms-block[data-padding="m"] {
		padding: var(--blocks-padding-m, 2rem);
	}

	.cms-block[data-padding="l"] {
		padding: var(--blocks-padding-l, 3rem);
	}

	.cms-block[data-padding="xl"] {
		padding: var(--blocks-padding-xl, 4.5rem);
	}

	@container (max-width: 42rem) {
		.cms-blocks[data-responsive="stack"] > .cms-block {
			grid-column: 1 / -1;
			grid-row: auto;
			margin-inline-start: 0;
		}
	}

	/*
	 * The gallery block carries the editor's settings on its container:
	 * `data-ratio` with `--ratio` fixes the tiles' shape, `data-crop` fills
	 * it instead of fitting the image in. Without them each image keeps
	 * its own shape.
	 */
	.cms-blocks-images[data-ratio] img {
		width: 100%;
		height: auto;
		aspect-ratio: var(--ratio);
		object-fit: contain;
	}

	.cms-blocks-images[data-crop] img {
		object-fit: cover;
	}
}
```

A block spans `--reserved` columns — its indent plus its colspan — and a margin pushes its own box past the indent, so the indent stays in the flow instead of naming an absolute column. The percentage in that margin resolves against the block's own grid area, which is exactly `--reserved` columns wide, so one column is `(100% + gap) / reserved` and the sheet needs no measurement of the container. A block too wide for the columns still free in its row wraps onto the next one, the way any grid item does.

Everything sits in the `cms.blocks` cascade layer, so **unlayered site CSS wins** over it without needing a more specific selector. The intended override points are:

- The spacing tokens — `--blocks-gap-s`, `--blocks-gap-m`, `--blocks-gap-l`, `--blocks-gap-xl` and `--blocks-padding-s` … `--blocks-padding-xl` — one length per token the editor may choose, set on `.cms-blocks` or anywhere above it; `none` is always zero. The sheet declares none of them and reads each with a fallback (1rem, 2rem, 3rem, 4.5rem), so a site sets only the ones it wants to change. The medium gap is what a field renders with when the editor chose nothing.
- `--blocks-gap` — the site's default gap, used when the editor chose nothing; a chosen token always wins over it, `none` included, so the canvas and the site agree. Set it on `.cms-blocks` or anywhere above it. It and the gap tokens have to be **context-independent lengths**, `rem` or `px`. A gap is resolved twice, once by the grid against the container and once by the indent margin against the block's own area, and the two agree only for a length that means the same in both places: a percentage does not (each resolves against its own box), and an `em` follows whatever font size the element it lands on has. Registering the property as a `<length>` would lift the restriction, but a registered property's initial value must be computationally independent and the `2rem` default is not, so the constraint stands.
- `--blocks-column-gap` and `--blocks-row-gap` — what the sheet resolves the tokens to; the indent margin reads the former. Read them, do not set them.
- The container threshold — redeclare the `@container` block at the width the design wants. The container itself is `.cms-blocks` (`container-type: inline-size`), so the query measures the blocks area, not the viewport.
- The gallery rules — `[data-ratio]` fixes the tiles' shape and `[data-crop]` fills it; both key on attributes the editor emits only when a gallery chose them, so a site without galleries or with its own gallery rules loses nothing by leaving them out.

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

- `colspan` and `rowspan` move into `layout` as they are; `colstart` becomes `layout.indent` (an offset, so `colstart: 3` is `indent: 2`). The legacy `colstart` named an absolute column and only ever placed a block that started its row; the offset is relative to the flow and renders such a block identically; `width` and the field's `columns`/`minCellWidth` meta are dropped.
- The block type ids become classes: the legacy `html` id and `richtext` map to `Cosray\Block\RichText`, `h1`–`h6` to one `Cosray\Block\Heading` with the level as its option, and the media, YouTube and iframe blocks to their type with the value moved into the type's field.
- The YouTube aspect ratio moves out of the block meta into the `Youtube` field's meta; `class` and `id` stay block meta; other meta keys are kept and reported.
- Blocks without a `uid` get one.
- Layouts are **copied, not clamped** — the field's schema is unknown at migration time, and readers clamp anyway.
- Blocks of an unknown type are left untouched and listed in the report.

The report is written to `blocks-migration-report.json` at the project root, with the counts `rows`, `updated`, `fields`, `blocks`, `types` (legacy id → count), `uidsGenerated`, `legacyRichtext`, `droppedMediaItems`, `droppedItems`, `metaKeys`, plus the lists `unknownTypes` (table, row, field, locale, index, type) and `unresolvedFieldTypes`. Three of them deserve a look after a run:

- `legacyRichtext` counts richtext blocks that carry no [richtext format envelope](richtext-format.md). They keep their bare value and render empty until migration `000000-000020` has covered them.
- `droppedMediaItems` counts items beyond the first in an `image` or `video` block — those types hold one item now.
- `unknownTypes` lists what was left behind; those rows still hold the legacy shape and will be dropped the next time the node is saved.

Two changes outside the blocks field come with the same release: a standalone `Iframe` field renders its code raw instead of escaped (it was unusable as an embed before), and a `Youtube` field validates its value as a video id (`[A-Za-z0-9_-]{1,64}`) on save, edits its aspect ratio through the field meta dialog — inside a block, through the block's settings dialog — and renders through its own `youtube` control, which shows the video's thumbnail and turns a pasted URL into the id.

Sites whose spans relied on the implicit 12 columns must add `#[Columns(12)]` to those fields — the column count is no longer stored per node, and without the attribute a field is a one-column stack.
