# Editor control vocabulary

Every field type describes its editor UI as a **control descriptor** returned by the field's `control(): Cosray\Field\Control` method and serialized into the field payload as `control: { name, props }`. The editor renders **primitive** controls as server-side Boiler views (plain HTML inputs) and the structural `group`/`repeater`/`entries`/`blocks` controls the same way. Everything else — named rich controls, cosray's own included — resolves server-side through the control registry to an element descriptor and is rendered by a **custom element** hosted in a form-associated `<cosray-host>` that carries the value into the form submission as one JSON leaf. The panel knows neither field type classes nor built-in control names.

Cross-cutting concerns are **not** part of the descriptor. Label, content-language selection, required marker, description, and width come from the field's other properties (driven by schema attributes such as `#[Label]`, `#[Required]`, `#[Translate]`, `#[Width]`) and are rendered by the shared editor views.

Fields declared by a class implementing `Cosray\Contract\Embedded` use the same descriptors and flat form names as direct fields. An embedded property without `#[Fieldset]` contributes ordinary wrappers at its declaration position. With `#[Fieldset]`, the editor groups its children in a semantic fieldset — at the top level and inside entry rows alike; `Label`, `Description`, and `Width` configure the group. Child widths are relative to the fieldset's inner layout. Fieldset metadata is serialized separately from the flat `fields` array, so controls require no fieldset-specific behavior.

## Value shapes

Field values are persisted as locale maps. The neutral locale key is `zxx`; translatable fields (`#[Translate]`) use real locale ids (`de`, `en`, ...). The table lists the shape of `value` per control name.

| Control | Builder | Props | Value shape |
| --- | --- | --- | --- |
| `text` | `Control::text(?placeholder)` | `placeholder?` | locale map of `string` |
| `textarea` | `Control::textarea()` | — | locale map of `string` |
| `number` | `Control::number(step:,min:,max:)` | `step?`, `min?`, `max?` | locale map of `number\|string` |
| `checkbox` | `Control::checkbox()` | — | locale map of `bool` |
| `option` | `Control::option(display:)` | `display: select\|radio` | locale map of `string` (options come from `#[Options]`) |
| `date` | `Control::date()` | — | locale map of `YYYY-MM-DD` |
| `time` | `Control::time()` | — | locale map of `HH:MM` |
| `datetime` | `Control::datetime()` | — | locale map of `YYYY-MM-DDTHH:MM` |
| `hidden` | `Control::hidden()` | — | locale map of `string` |
| `iframe` | `Control::iframe()` | — | locale map of `string` |
| `youtube` | `Control::youtube()` | — | locale map of `string` — a video id; the control shows the video's thumbnail and reduces a pasted YouTube URL to its id |
| `group` | `Control::group(fields)` | `fields: {key,label?,control,width?}[]` | `zxx` map of object keyed by `key` |
| `repeater` | `Control::repeater(item,min:,max:)` | `item`, `min?`, `max?` | `zxx` map of list of item values |
| `entries` | `Control::entries()` | `entryTypes` (built from `#[Allows]`), `min?`, `max?` | `zxx` map of `{uid, type, fields}[]` |
| `blocks` | `Control::blocks()` | `blockTypes` (every allowed descriptor, with `icon?`), `commonTypes?` (type IDs in menu order; the first six when absent), `columns`, `min`, `responsive`, `meta` | locale map of `{uid, type, layout, fields, meta?}[]` |
| `element` | `Control::element(tag, module)` | `tag`, `module` | whatever the field's `structure()` defines |

Named rich controls (resolved to elements server-side; cosray's built-ins ship as custom elements under `cosray:` modules):

| Control | Builder | Element | Value shape |
| --- | --- | --- | --- |
| `code` | `Control::code()` | `cosray-code` | locale map of `string`, `meta.syntax` (syntaxes from `#[Syntax]`) |
| `richtext` | `Control::richtext()` | `cosray-richtext` | locale map of richtext documents; `field.tools` (toolbar set from `#[Tools]` / `richtext.tools`) |
| `image` | `Control::image()` | `cosray-image` | locale map of `{file, meta?}[]`; a gallery keeps its `ratio` and `crop` settings in the field meta |
| `file` | `Control::file()` | `cosray-file` | locale map of `{file, meta?}[]` |
| `video` | `Control::video()` | `cosray-video` | locale map of `{file, meta?}[]` |
| _custom_ | `Control::named('acme-map')` | via `Registrar::control()` | whatever the field's `structure()` defines |

### Content language and fallback previews

A node with any translated content has one **Content language** select in its inspector. It switches every translated primitive and element control, every translated field inside Entries and symmetric Blocks, and each locale-specific list of an asymmetric Blocks or media field. The select is editor state rather than content: changing it neither marks the form dirty nor changes a stored value.

An empty selected translation displays the first non-empty value from that locale's configured `fallback` chain, then `zxx` when present. The preview is separate from the editing value and names the locale that supplied it. Native text controls use a placeholder; richtext and code use a read-only content layer; asymmetric media and Blocks show an inert rendering of the source list. Image `alt`, `caption` and `title` also consult asset-catalog metadata after per-use metadata. Empty strings, semantically empty richtext documents, media lists without a usable uid, and empty block lists count as missing.

Focusing an empty text, metadata, richtext, or code editor hides its preview; block previews also hide while the target list has focus. The target remains empty throughout. Switching language, focusing and blurring, opening or cancelling a metadata dialog, and saving another field never copy fallback text, documents, assets, metadata, or block rows into the target locale. Adding media or a block operates on the selected locale's empty list and removes the preview only after that locale gains its own item.

Element payloads carry `locales.all[].fallback` with the same chain the server uses. Custom translated controls receive the selected `locale` and this metadata but own their empty-value test and preview UI; they must never write a resolved fallback into their `value` map merely to display it.

### Richtext toolbar

The richtext toolbar shows a configured set of tools, resolved as: the field's `#[Tools(...)]` attribute, else the project's `richtext.tools` config key, else `Tool::DEFAULT` (undo, redo, bold, italic, strike, h2, h3, bullet list, ordered list, link). The vocabulary is the `Cosray\Schema\Tool` enum — `Undo`, `Redo`, `H1`–`H3`, `Bold`, `Italic`, `Strike`, `Sub`, `Sup`, `Align`, `BulletList`, `OrderedList`, `Blockquote`, `Hr`, `Link`, `Image`, `Br`, `Clear`, `Source` — and both the attribute and the config key replace the default set rather than extending it. The list is a set: the toolbar renders whatever is picked in its own canonical order, duplicates collapse.

Attribute arguments mix single cases with lists (PHP forbids unpacking there), so the preset constants on the enum — `Tool::DEFAULT`, `Tool::MINIMAL` (bold, italic, link), `Tool::INLINE` (bold, italic, strike, link, bullet list, ordered list, clear), `Tool::ALL` — seed a set without re-listing it:

```php
use Cosray\Schema\Tool;
use Cosray\Schema\Tools;

#[Tools(Tool::DEFAULT, Tool::Align, Tool::Source)]  // the default plus two
public RichText $body;

#[Tools(Tool::MINIMAL)]
public RichText $teaser;
```

```php
// Project-wide, in app settings, as Tool cases or their string values;
// the attribute still wins per field.
'richtext.tools' => ['undo', 'redo', 'bold', 'italic', 'link'],
```

Inside a block the resolution is: the block type property's own `#[Tools]`, else the blocks field's `#[Tools]`, else `Tool::INLINE`. The project's `richtext.tools` describes the full toolbar and does not reach into blocks.

The paragraph-class and text-style dropdowns are not tools: they appear exactly when the project declares `richtext.classes` / `richtext.styles`, as before.

Limitations (v1): `group` and `repeater` support only primitive sub-controls (`text`, `textarea`, `number`, `checkbox`, `option`, `date`, `time`, `datetime`, `hidden`) and neutral-locale values.

A group sub-control may declare a `width` percentage; sized sub-controls share a row and stack at full width when the group container gets narrow (pure CSS, container queries). A date range is two 50% dates:

```php
public function control(): Control
{
    return Control::group([
        ['key' => 'from', 'label' => 'Von', 'control' => Control::date(), 'width' => 50],
        ['key' => 'to', 'label' => 'Bis', 'control' => Control::date(), 'width' => 50],
    ]);
}
```

## Entries

An `Entries` field renders server-side as a **typed repeater**: each stored row is a group of regular field wrappers, built from the row type's field table in the descriptor's `entryTypes` props. Everything a top-level field has works inside a row the same way — labels, descriptions, meta dialogs, fieldsets — because rows reuse the same wrapper views, just at a deeper form name. In the node editor, the sidebar's content-language selector changes every translated sub-field in every row together. Rows retain all locale values in the form; changing the selector only changes which variants are visible.

```text
content[f][value][zxx][i][uid]                       hidden row identity
content[f][value][zxx][i][type]                      hidden row type (FQCN)
content[f][value][zxx][i][fields][sub][value][lo]    primitive sub-field, per locale
content[f][value][zxx][i][fields][sub][json]         element sub-field (cosray-host leaf)
content[f][value][zxx][i][fields][sub][meta][k][lo]  sub-field meta dialog
```

Adding, removing, reordering and collapsing rows happens entirely client-side: the editor page carries one inert `<template>` per allowed entry type, the add button stamps a copy into the form, rows reorder by dragging their grip (or through the row menu's move up/down), and renumbering keeps names dense. Element controls inside a stamped row upgrade and load on insertion like any other. No server round trip is involved until save.

Stored rows render **collapsed to a summary line** and open their form beneath it when clicked; several rows can be open at once, and a freshly added row opens right away. The summary needs no configuration — it follows the entry type's field order: the first text-like sub-field with content (`Text`, `Textarea`, `Number`, `Decimal`, or a `RichText` flattened to plain text) is the primary line, the next one the secondary line, and the first `Image` sub-field carrying an asset supplies the thumb. Translated fields contribute the default locale's value, falling back to the neutral locale and then to any locale with content; a row without any text shows its type label. While a row is open, each summary line follows the input of the sub-field it was drawn from as the editor types; a line drawn from a richtext keeps what the server rendered until the next load.

Row **uids** exist for patch matching: the client fills a fresh row's uid on stamping (13-char lowercase word-safe format; the server backfills any row arriving without one). Entry uids are internal identifiers — deliberately fixed-format, not governed by the `uid.*` config that shapes node and asset uids.

**Saving** replaces the row list wholesale — order is submission order, missing rows are deleted, rows of types the field does not allow are dropped. Each surviving row is matched to its stored counterpart **by uid** and its sub-fields are patched individually, exactly like top-level fields (element leaves via `[json]`, primitives per locale, meta per key). One boundary to know: at the top level, unknown keys in stored content survive a save untouched; **inside entry rows they do not** — storing validates each row's fields and keeps only declared keys. The supported places for extra data inside entries are declared fields and declared meta.

Limitations (v1): `#[When]` conditions are not emitted for sub-fields inside entries (a condition would otherwise evaluate against a same-named top-level field); an entry type must not contain another `Entries` field or a `Blocks` field (both rejected at boot); a summary line drawn from a richtext sub-field refreshes on the next full load, not while typing.

## Field meta

A field's meta map gets an editor UI by overriding `metaControl()` with a `group` whose sub-control keys name the meta entries:

```php
public function metaControl(): ?Control
{
    return Control::group([
        ['key' => 'cssClass', 'label' => 'CSS class', 'control' => Control::text()],
        ['key' => 'tone', 'control' => Control::option()->prop('options', ['calm', 'loud'])],
    ]);
}
```

The field wrapper then shows a "Meta" button opening a per-field dialog; entries submit as `content[{field}][meta][{key}][zxx]` through the merge patch — meta keys the group does not know survive untouched. Inside a block the row's settings dialog hosts the group instead, under the field's label when the block labels its fields, so no meta button sits in the content. Element controls keep managing their meta themselves (through the `cosray-change` detail); `metaControl()` is for native fields.

An element control reports its meta in the same `cosray-change` detail as its value — `{ value, meta }` — and the field's `metaShape()` validates it on save like any other meta. The image element inside an images block is the built-in example: it renders the gallery's aspect ratio and crop into its settings slot and reports them as `meta: { ratio: { zxx: '4/3' }, crop: { zxx: true } }`, which `Field\Image` validates and `Value\Images::ratio()` and `crop()` read on the site.

A `blocks` field uses the same dialog for its **rows**: its descriptor carries a `meta` prop — a `group` with the `class` and `id` text controls — and every block row renders it behind the gear in its header strip, submitting as `content[{field}][value][{lo}][{i}][meta][{key}][zxx]`. It is one group for every block type; a descriptor without the prop stores no block meta at all.

## Save transport

The editor is one plain HTML form; every control participates through its form name — primitives directly, element controls through their host's `[json]` leaf. At submit time the panel re-encodes the collected form data into a single nested JSON body (`Content-Type: application/json`): the bracket names are parsed client-side with the exact `parse_str()` semantics pinned in `contract/form-names.json`, so the server receives the identical tree either way. This lifts PHP's `max_input_vars` cap off the editor — a content-heavy node (entries rows × sub-fields × locales) would otherwise exceed the default of 1000 input keys and be **silently truncated**, and since entries rows are replaced wholesale on save, truncation would delete content.

Two safeguards back this up:

- The form renders a sentinel input (`_complete`) as its last control. A submission that lost its tail — a form-encoded POST past `max_input_vars`, a mangled body — is refused with an error instead of being saved, on both transports.
- The native urlencoded submit remains supported server-side and stays covered by the same sentinel guard.

Plugins are unaffected: element controls keep submitting through their host's form value, and neither the name scheme nor the value shapes change with the transport.

## Save response

A save answers with out-of-band swaps only — the form itself is never re-rendered. The response fragment (`panel/views/editor-save.php`) targets four fixed ids: `#editor-status` (the status chip; `data-saved` on it is what stands the unsaved-changes guard down), `#editor-errors` (the validation summary, next section), `#editor-published` (the published badge, on successful saves of renderable nodes) and `#editor-preview` (the preview overlay, when the save requested one). The controller feeds the fragment a fixed payload: `saved`, `message`, `errors` (message + path pairs), `published`, `renderable`, `preview`.

These ids and keys are an **internal contract**, pinned by the e2e save tests. It is deliberately closed to plugins; a sanctioned way for plugin actions to ride the save round trip is part of the future action-slot design.

## Validation errors

The editor form is never re-rendered after a failed save (client state stays the source of truth). Instead the save response swaps the error summary out-of-band, and each issue carries the sire data path of the failing value:

```html
<button type="button" data-error-path='["content","title","value","de"]'>
	Title (Deutsch) is required
</button>
```

Because form names mirror the data structure, the `errors` behavior resolves that path to its control — exact name first, then shrinking prefixes, which is how an issue pointing inside an element control's value finds the host's `[json]` leaf. It then marks the field: `data-invalid="true"` on the `.cms-field` wrapper, `aria-invalid` + `aria-describedby` on native controls, an inline `.cms-field-error` message below the control, an error badge on the node's content-language selector (or the local locale tab outside a node editor), and on the meta button for issues inside the meta dialog. Summary items are jump links: they reveal the target (switch the content language, expand collapsed entries rows, open the meta dialog) and focus it. Editing a field clears its marks; the summary stays until the next save.

Theming hooks: `.cms-field[data-invalid='true']`, `.cms-field-error`, and `.has-error` on tabs and meta buttons, all in `@layer panel`. Element controls receive field-level marking only — the wrapper is marked, but the panel does not reach inside a host to point at a specific locale or sub-value; an element wanting finer error display can style itself when its host's field wrapper carries `data-invalid`.

## Blocks

A `Blocks` field renders server-side as a **typed repeater with a grid**: the same row machinery as entries, plus a layout per row. Block types are plain PHP classes implementing `Cosray\Contract\Block` — fields for the schema, a `render()` for the frontend; plugins add one to the default offer list with `Registrar::blockType(MyBlock::class)`, a field restricts its own with `#[Allows(MyBlock::class)]`. The model, the stored shape, the rendering contract and the reference stylesheet live in [docs/blocks.md](blocks.md); this section is the editor side.

```text
content[f][value][{lo}][i][uid]                       hidden row identity
content[f][value][{lo}][i][type]                      hidden row type (FQCN)
content[f][value][{lo}][i][layout][colspan|rowspan|indent]  hidden, stepped by the toolbar
content[f][value][{lo}][i][fields][sub][value][lo]    primitive sub-field, per locale
content[f][value][{lo}][i][fields][sub][json]         element sub-field (cosray-host leaf)
content[f][value][{lo}][i][fields][sub][meta][k][lo]  sub-field meta dialog
content[f][value][{lo}][i][meta][class|id][zxx]       block settings dialog
```

`{lo}` is the list's locale: an **asymmetric** field renders one list per locale and its sub-fields are neutral; a **symmetric or untranslated** field renders a single `zxx` list. The node-wide content-language selector switches asymmetric lists and translated sub-fields in shared rows together. When the selected asymmetric list is empty, the first populated fallback list is also shown as an inert preview; its rows keep their original form names and values, while add actions continue to target the selected empty list.

No field inside a block carries the **required marker** that other fields append to their label as translated `(required)` text. The block's descriptor simply does not claim it; validation is unchanged, since the shape is built from the field and not from the descriptor.

A block with a **single visible field** renders that field without its label: the block's own label already names it. The label stays in the markup for screen readers. A sub-field's meta group, where it has one, moves into the block's settings dialog after the layout and the block meta, so nothing but the control is left in the content; an error in it badges the block's gear. `#[Labels]` on the block type brings the label back, and a type with two or more fields always labels them.

Such a **bare** block (`.block.is-bare`) is content first: its control renders without border or background, at reading size, with the field's `#[Placeholder]` while empty; a hairline appears on hover and the focus ring when it is active, which is what says the area is editable. Text areas grow with their text where the browser supports sizing a field to its content. The iframe code box keeps its inverted look, since code reads as code. The YouTube block shows the video's thumbnail above its id once an id is stored and follows the input as it is typed — a full eleven-character id only, so no image is requested per keystroke — and a pasted YouTube URL becomes its id on landing; the thumbnail is fetched from YouTube's image host by the editor's browser. The frame the panel draws around a rich text control goes the same way. An image or video inside a block is a **figure**: the picture, or the player, at block width, with replace, library and remove on a bar along its edge that appears on hover, and the dropzone card while empty; an image shows its caption underneath when it has one. The alt text and caption form lives in the block's settings dialog, in the slot the host hands the element (see [The element contract](#the-element-contract)); a media field outside a block keeps its card. Rich text inside a block — bare or not — edits with a **bubble** instead of the toolbar: it appears over a selection and carries the block's resolved tools (see [Richtext toolbar](#richtext-toolbar)). Block styles — headings and quote, where enabled — fold into one menu in it; tools that act on the document as a whole (undo, redo, source, image, rule, break, align) have no button there. Lists also toggle from the keyboard with Mod+Shift+7 and Mod+Shift+8, and by typing `-` or `1.` at the start of a line. A block with two or more fields keeps its labelled, compact form — unless the type brings an editor view of its own, as the built-in heading does: its level is a quiet chip reading "H2" before the text, the text sits at that level's size, and both labels are for screen readers only. The row picks such a view by block class (`panel/views/field/blocks/types/`); it receives what the generic field list receives.

All blocks of a field sit on **one canvas**. Rows are **never collapsed** and carry **no header**; nothing marks a block at rest in a one-column list, and a grid keeps its cells legible with a faint dashed outline. A block's chrome is a group hanging over its top right corner: a drag grip, the type label, a gear opening the block's settings dialog, and a menu with move up, move down, duplicate and remove. One block per field is **active** — the focused one, or the hovered one while none is focused — and only it is bordered and shows its chrome, its `+` and its handle bars; hover and focus as independent reveals would show two blocks' controls at once. A click on a block's own ground focuses it; a click on a control inside it keeps its meaning. The controls are unclickable while hidden, so they never intercept a click meant for the block beneath them. An empty field is the canvas with its add bar alone.

At rest a block shows only its content. Its controls appear when the block is active; content language is selected once in the node sidebar rather than on the row. New blocks come from one `+` per block, hanging over its start corner opposite the chrome, which inserts before it — above it in a list, before it in document order in a grid, where a seam between wrapped cells cannot say where a block would land but the block's start corner can. Together with the permanently visible `+` at the foot, which appends, that covers every insertion point once. The corner keeps the `+` off the edges, which belong to the resize handles, the way a table editor puts its insert control at the end of a column boundary rather than on it. With several allowed types the `+` opens a short menu of icon and name choices, with one it inserts at once, and with none there is nothing to add. The menu shows the first six allowed types unless `#[Common(...)]` or `Blocks::common(...)` picks an ordered subset; see [common choices](blocks.md#common-choices). Whenever other allowed types remain, **More blocks…** follows the menu and opens a catalog of every allowed type, common ones included, searchable by label or handle.

The catalog is one inert `<template>` per field — per locale on an asymmetric field — opened in the shared modal. It opens fresh each time, search empty and focused, and remembers the field and row it was opened for: a choice inserts before that row, or appends from the foot, and does nothing if the row or field has meanwhile gone or the content language changed. Cancelling changes nothing and returns focus to the `+`.

Adding, removing, reordering and renumbering is the shared repeater behavior, stamping from one inert `<template>` per allowed type; a stamped row focuses its first visible input, or itself when it has none, staying focusable only until focus moves on. **Duplicate** stamps the block's own template right after it and copies the live values control by control — layout included, a media reference rather than the file, and for an element control such as rich text its payload as edited so far, read off the host rather than the last server-rendered value — under a fresh uid.

The editor grid mirrors the frontend contract — `--columns` on the container, `--colspan`/`--rowspan`/`--indent`/`--reserved` (plus `data-indent`) on the row — so a block sits where the site will place it. The **settings dialog** holds the layout as three number inputs — width, rows, indent — above the block's `class`/`id` meta, and opens on any multi-column field, meta or not (a one-column field has no layout to edit and shows the gear only for the meta). A value applies as it is typed, within the field's own bounds: `span` ∈ `[min, columns − indent]`, `rows` ∈ `[1, 6]`, `indent` ∈ `[0, columns − span]`. Each dimension is capped by the room the other two leave and never moves them, so widening stops at the grid's edge instead of pulling the indent in; a value that is out of range or half typed waits until the input commits. The inputs are unnamed mirrors of the row's hidden layout inputs, which are what the form submits. After the layout and the meta groups the dialog holds one empty slot per element sub-field (`data-settings-slot="{sub}"`), which the host hands to the element as its `settings` property so the element can keep its secondary controls out of the content.

A multi-column field also puts a **resize handle on each edge** of a block: a grab area the width of the gap, marked by a short bar that appears on hover and focus-within. The end edge grows the span and lets the grid wrap the block once it no longer fits beside its neighbours, the start edge trades indent against span so the block stays where it is, and the bottom edge changes the **row span** — not a height. Grid rows size to their content, so there is no pixel height for the bottom edge to follow; it therefore ratchets one row per 100px of travel rather than tracking the pointer, and the block's real height is whatever the tracks it now spans happen to be. Columns do track the pointer. A gesture writes the same hidden inputs the dialog writes and dispatches one `change` when it ends. The **keyboard** reaches the same edges from the focused grip, which is in the tab order on a multi-column field: Alt with the left and right arrows moves the end edge, Alt and Shift with them the start edge, Alt with up and down the bottom edge; each stops where its handle stops, and the key is consumed so Alt with an arrow never turns into the browser's history.

**Saving** works like entries: the list is replaced wholesale, rows are matched by uid and their sub-fields patched individually, rows of a disallowed type are dropped. On top of that the layout is cast to ints and clamped into the field's grid — a stored layout a narrower field cannot hold saves back clamped, where the shape would reject it — and the block meta is patched against the descriptor's `meta` group, so unknown meta keys survive.

Limitations (v1): the same as entries — no `#[When]` conditions on sub-fields, no nested typed repeaters (a block type may contain neither `Blocks` nor `Entries`, and an entry type may not contain `Blocks`); the block meta group is fixed to `class` and `id`; dragging is the only reorder that is not keyboard-reachable (the menu's move up/down is), while resizing is.

## Element controls

Controls beyond the primitive vocabulary are rendered by **custom elements** (web components). A field either uses a one-off element:

```php
public function control(): Control
{
    return Control::element('acme-color-picker', 'acme-shop/controls.js');
}
```

or a **named control** registered once and reusable across fields:

```php
// in the plugin's register():
$cms->control('acme-map', 'acme-map-picker', 'map.js');

// in any field:
public function control(): Control
{
    return Control::named('acme-map');
}
```

Named controls are resolved server-side to element descriptors before serialization; the editor never sees the name. Later registrations win, so a plugin may replace a built-in editor by registering its name (e.g. `richtext`). Cosray's own rich controls are registered through the same registry and shipped as custom elements — they are the reference implementations.

### Module values

| Form | Served from |
| --- | --- |
| `{pluginId}/{file}` | the plugin's asset dir via `{panel}/vendor/{pluginId}/{file}` (`Registrar::control()` prefixes the plugin id automatically) |
| `cosray:{entry}` | the panel static assets (`{panel}/static/elements/{entry}.js`, or the Vite dev server when `COSRAY_PANEL_DEV=1`). `cosray` is a reserved plugin id. |
| `https?://...` | used as-is |

Modules load once via dynamic `import()` and must define their custom element at top level:

```js
customElements.get('acme-color-picker') ||
    customElements.define('acme-color-picker', class extends HTMLElement { ... });
```

Hand-written ES modules are sufficient — no build step required.

### The element contract

- The host assigns JS **properties** (not attributes) on the element and re-assigns them when they change:
  - `value` — the stored value in the exact shape the field's `structure()` persists (usually a locale map). Treat repeated assignments as idempotent.
  - `meta` — the field's meta map when the structure has one (e.g. code syntax), else `undefined`.
  - `field` — the full field properties object (`name`, `label`, `required`, `translate`, `options`, ...). Inside a block it also carries `presentation: 'block'`: render as content — no frame of your own, the field's placeholder while empty — and keep whatever is not the content out of the inline area.
  - `node` — the node uid; `''` while creating a node that has not been saved yet.
  - `locale` — the **currently selected editing locale**. In a node editor it follows the one content-language selector in the sidebar, including for controls nested in Entries or symmetric Blocks. Other panel screens may own a local selector. When `field.translate` is true, render `value[locale]`; neutral controls keep using `zxx`.
  - `locales` — `{ default: string, all: {id, title, fallback?}[] }`.
  - `assets` — resolved catalog data for every asset uid the entry references: `{ [uid]: { filename, url, kind, mime?, width?, height?, meta? } }`. Media items in `value` are `{uid, meta?}`; previews resolve uids through this map. Upload responses carry the same data for freshly added assets.
  - `settings` — an element to render secondary controls into, assigned only when an owner around the control offers it a slot: today the settings dialog behind a block's gear, which holds one slot per element sub-field. Mount into it once, on assignment; the host never re-assigns it. Without a slot the property is absent and everything renders inline.
- The element reports every edit by dispatching a composed, bubbling custom event with the **full new value** (and optionally meta) in the same shape:

  ```js
  this.dispatchEvent(
  	new CustomEvent("cosray-change", {
  		detail: { value, meta },
  		bubbles: true,
  		composed: true,
  	}),
  );
  ```

  Dispatch only from user-initiated edits, never in response to a property assignment.

## Conditional fields

A field can be tied to a sibling field's value with the `When` schema attribute:

```php
#[When('multiDay')]                    // truthy
#[When('layout', 'hero')]              // equality
#[When('template', in: ['a', 'b'])]    // membership
#[When('teaser', op: 'empty')]         // explicit operator: truthy, eq, neq, in, empty, notEmpty
public Date $endDate;
```

The editor hides an inactive field (its inputs stay in the form, `required` is suspended) and shows it again the moment the condition holds — the stored value is **never** cleared by toggling. On the frontend and API the same condition is enforced at read time: an inactive field presents as empty, without any template code checking the source field. `Field::raw()` deliberately bypasses the enforcement for consumers that need the dormant value.

Limitations (v1): condition sources must be primitive, non-translated fields (checkbox, option, text, number); conditions are not emitted for sub-fields inside repeaters or entries; combining `#[When]` with `#[Required]` still enforces required on save while the field is inactive.

## The window.Cosray bridge

Panel editor pages install `window.Cosray` from the embedded system payload, a versioned runtime API for element controls — cosray's own and plugin-shipped ones alike:

```ts
window.Cosray = {
	version: 1,
	system(): { locale, defaultLocale, locales, customLocales, prefix, assets, debug, allowedFiles },
	upload(type: 'image' | 'file' | 'video', file: File): Promise<{ok, error?, uid?, filename?, url?, mime?, width?, height?}>,
	modal: { open(render: (host: HTMLElement) => cleanup?, options?): { close() } },
	toast: { success(message), error(message) },
};
```

`upload()` posts to the pool endpoint `POST /media/{type}` with the session's CSRF token — elements never handle credentials. It returns the catalog asset (`uid`, `url`, `filename`, ...); store `{uid}` in the field value and keep the rest for previews. `GET /media/library` lists the catalog for reuse pickers (`kind`, `q`, `since`, `page` parameters). `modal.open()` hands the callback an empty host element inside a native modal `<dialog>`; render DOM into it and optionally return a cleanup function. The callback and returned `close()` handle are unchanged in bridge version 1. The bridge only exists on editor pages — elements used elsewhere should degrade or show a hint. Check `window.Cosray?.version === 1` before relying on it.

### Modal controls

The panel targets the latest stable Chrome/Edge, Firefox, Safari, and iOS Safari. Native dialogs provide modality and focus containment. Escape and a pointer gesture that starts and ends on the backdrop dismiss a dialog. `hideClose` hides only the built-in close button, not these dismissal paths. Closing cancels a confirmation; only its explicit confirm action may proceed.

Optional bridge options are `{ hideClose?, label?, size?: 'compact' | 'wide', owner?: HTMLElement }`. Use `label` for content without a heading. Otherwise a heading supplies the accessible name. `owner` identifies the control whose removal should dismiss the modal; supply it for asynchronous callers or controls whose trigger can disappear independently of the screen. Without it, the bridge uses the focused opener or current system-payload element.

Renderer cleanup runs exactly once on every close path, including owner removal and navigation. Close the modal **before** an action that focuses new content or runs a richtext command, so focus restoration cannot override the action. Nested dialogs return focus to the underlying dialog. A failing renderer cannot leave a modal host behind.

Internal content uses `.modal-header` with a `.modal-title` heading, `.modal-body`, and `.modal-footer`; the Svelte wrappers in `panel/src/components/modal/` emit these same parts. `data-dialog-focus` designates initial focus, especially the safe action in a confirmation. Otherwise the first usable input receives focus. These markup names are internal, not a new plugin slot API. Plain TypeScript can render the same content through the bridge without mounting Svelte.

Built-in action menus use the shared [action-menu markup contract](panel-styles.md#action-menus) in both PHP and Svelte. The panel closes a menu and restores its opener **before** invoking the selected action, so that action can open a modal without losing its initial focus. Keep row/field ownership attached to the DOM instead of moving menu forms into a separate host. The styleguide demonstrates server-rendered menu-to-dialog transitions and nested menus/dialogs.

Server-rendered field/block settings and collection bulk confirmations use the same lifecycle in `panel/src/lib/dialogs.ts` and the same frame. Settings dialogs stay inside their editor form; closing keeps live settings values rather than reverting them. Media metadata dialogs retain their separate draft and explicit Apply/Cancel behavior.

Browser-owned `beforeunload` warnings and the synchronous dirty-navigation / `hx-confirm` prompts remain intentional exceptions. They are not routed through the asynchronous dialog API.
