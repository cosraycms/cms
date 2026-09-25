# Editor controls

This reference describes the current boundary between PHP field definitions, server-rendered forms, and custom elements. It is not a fixed panel design: presentation can evolve while preserving correct submission, accessible interaction, and stored values.

[Field\Control](../src/Field/Control.php) describes a field's editor as `{name, props}`. Primitive and structural controls render through [Boiler views](../panel/views/field/); named rich controls resolve through the registry to custom elements inside a form-associated `<cosray-host>`. Labels, descriptions, required state, translation, and layout come from field properties rather than the control descriptor.

## Value shapes

Fields persist locale maps: `zxx` is neutral; translated values use configured locale IDs. The field's `structure()` and validation shape define the actual payload.

| Control | Value per locale |
| --- | --- |
| `text`, `textarea`, `hidden`, `iframe` | String |
| `number` | Number or numeric string |
| `checkbox` | Boolean, or boolean/null when nullable |
| `option` | Option value; select or radio presentation |
| `date`, `time` | Local date or time string |
| `datetime` | UTC RFC 3339 instant |
| `youtube` | Video ID |
| `group` | Object of sub-control values under `zxx` |
| `repeater` | List of item values under `zxx` |
| `entries` | List of `{uid, type, fields}` under `zxx` |
| `blocks` | List of typed rows with layout; see [Blocks](blocks.md#stored-shape) |
| `element` | Whatever the field defines |

Named built-ins resolve to the `cosray-code`, `cosray-richtext`, `cosray-image`, `cosray-file`, `cosray-video`, and `cosray-reference` elements. Code values are strings with syntax in field meta; richtext uses the [structured format](richtext-format.md); media values are `{uid, meta?}` lists; references are ordered neutral `{uid}` lists. [Media](media.md) explains catalog lookup and metadata fallback.

`DateTime` writes normalize offsets to UTC whole seconds. The panel converts between that instant and `datetime-local` input using `meta.timezone`, defaulting to UTC. `Date` and `Time` do not carry offsets. The YouTube control accepts a URL or video ID through **Add video** or Enter, then shows a player from `youtube-nocookie.com` without autoplay. **Replace video** immediately clears the current selection and returns to the empty input. Adding another video uses the same **Add video** or Enter action as a fresh field. Only the selected ID is submitted, never a pasted URL or an unconfirmed draft. Loading the player contacts YouTube; the privacy-enhanced origin does not eliminate third-party requests.

### Groups, repeaters, and fieldsets

`Control::group()` and `Control::repeater()` currently support neutral primitive sub-controls. Group parts can carry width percentages. Embedded fieldsets are separate schema layout metadata: field names stay flat and controls need no embedded-object behavior. Entries and block rows reuse those field declarations. See [embedded fields](content.md#embedded-fields).

### Reference fields

`#[Pick]` determines the eligible set server-side. The picker searches titles across that set and pages results; it does not accept client-defined eligibility rules.

`#[Limit(max: 1)]` gives a single choice. Typing changes the search, not the reference; selecting replaces it, clearing removes it, and dismissing restores the selected title. Multiple-reference fields keep a list of selected values, omit them from search choices, and stop accepting new ones at the limit. Loading, errors/retry, no eligible entries, and no search matches are distinct states. See [keyboard behavior](panel-keyboard.md#reference-fields) and [Reference](../src/Field/Reference.php).

### Checkbox fields

`Cosray\Field\Checkbox` is two-state by default. `#[Nullable]` allows explicit null, presented as Not set/Yes/No. `#[StateLabels]` customizes translated labels without changing stored values:

```php
use Cosray\Field\Checkbox;
use Cosray\Schema\Nullable;
use Cosray\Schema\StateLabels;

#[Nullable, StateLabels(true: 'Enabled', false: 'Disabled', null: 'Automatic')]
public Checkbox $enabled;
```

New ordinary fields default to false, nullable ones to null; `#[DefaultValue]` overrides creation defaults. `structure()` applies a default, whereas `structure(null)` preserves explicit null on a nullable field. Blueprint and nested row values also distinguish omission from explicit null. An omitted patch leaves the value untouched; an explicit null clears it.

`Value\Boolean::unwrap()` and `json()` preserve null for nullable fields. `isset()` is false for null but true for a stored false. Ordinary fields retain their two-state read behavior, including legacy null read as false. Consumers opting into nullable values must handle `bool|null`.

A required checkbox accepts false: requiring an answer is different from requiring consent. `#[Nullable, Required]` rejects null/missing values. `Nullable` and `StateLabels` currently apply only to Checkbox fields. `#[When]` still treats false and null alike as empty. See [Checkbox](../src/Field/Checkbox.php) and its tests for defaults and validation.

### Content language and fallback previews

One content-language selection switches translated fields across the screen, including nested entries and blocks. It is editor state, not stored content: changing it does not mark the form dirty. The browser remembers the choice. Dialogs can mirror it by dispatching a bubbling `content-locale:select` event with `detail.locale`.

An empty selected translation previews the first non-empty value in its configured fallback chain, then `zxx`. The preview is separate from the editable value and identifies its source locale. Image metadata can additionally fall back to catalog metadata. Native text, richtext, code, and media controls use different presentation mechanisms; asymmetric block previews are inert.

Switching language, focusing or blurring, cancelling a dialog, and saving another field must not copy fallback content into the selected locale. Adding media or a block operates on the target's own list. Custom controls receive `locales.all[].fallback` and own their empty-value test and preview UI; resolving a fallback for display is not a value change.

### Richtext tools

Tool selection resolves from the field's `#[Tools(...)]`, then `richtext.tools`, then `Tool::DEFAULT`. The selection replaces rather than extends the default. Preset arrays can appear alongside enum cases in the attribute:

```php
use Cosray\Schema\Tool;
use Cosray\Schema\Tools;

#[Tools(Tool::DEFAULT, Tool::Align, Tool::Source)]
public RichText $body;
```

Inside a block, resolution is the block type property's own `#[Tools]`, then the blocks field's `#[Tools]`, then `Tool::INLINE`. Project toolbar settings do not override that block default. The current block editor uses a selection bubble rather than the standalone toolbar, so document-level commands may have no button there.

[Tool](../src/Schema/Tool.php) defines the vocabulary and presets. Paragraph-class and text-style choices come from `richtext.classes` and `richtext.styles`, independently of the tool set. Hiding a tool does not remove its stored content semantics.

## Entries

An Entries field is a server-rendered typed repeater. Allowed schemas supply row templates; client-side behavior stamps, removes, reorders, and renumbers rows. Element controls upgrade in inserted rows just as on a full page load.

```text
content[f][value][zxx][i][uid]
content[f][value][zxx][i][type]
content[f][value][zxx][i][fields][sub][value][lo]
content[f][value][zxx][i][fields][sub][json]
content[f][value][zxx][i][fields][sub][meta][key][lo]
```

Row UIDs match submitted rows to stored rows across reordering. They are internal 13-character IDs, independent of node/asset UID configuration. Submission order is row order; missing rows are deleted and disallowed row types dropped. Surviving sub-fields are patched individually. Unlike top-level node content, unknown stored field keys inside entry rows do not survive validation; additional persistent values belong in declared fields or supported meta.

Rows currently have collapsible summaries derived from their fields; [EntrySummary](../src/Panel/EntrySummary.php) defines that selection. This presentation is independent of their storage contract. Nested typed repeaters are not supported, and sub-field `#[When]` conditions are not emitted for the editor.

## Field meta

Native fields can expose metadata through `metaControl()`:

```php
public function metaControl(): ?Control
{
    return Control::group([
        ['key' => 'cssClass', 'label' => 'CSS class', 'control' => Control::text()],
    ]);
}
```

Values submit as `content[field][meta][key][zxx]`. Unknown meta keys survive the merge patch. A block can host its sub-fields' meta controls in its settings dialog rather than in the inline content.

Elements report optional field meta alongside value through `cosray-change`; the field's `metaShape()` validates it. Gallery ratio/crop settings are one example. Block row and field spacing metadata are described in [Blocks](blocks.md#spacing).

## Save transport

Every control participates in the editor form through its name; element hosts contribute a `[json]` leaf. At submit time, [form-json.ts](../panel/src/lib/form-json.ts) encodes the collected bracket names into a single nested JSON body. Its parsing follows PHP's `parse_str()` for generated names, with shared cases in [contract/form-names.json](../contract/form-names.json).

This avoids `max_input_vars` silently truncating a large urlencoded form. Because row lists are replaced on save, truncation could delete content. Both JSON and supported urlencoded submissions require `_complete: "1"`, rendered as the form's final control; the server refuses a body without it.

[FormPatch](../src/Panel/FormPatch.php) merges values into stored content. `#[Immutable]` fields are ignored on save; their editors should expose reading rather than offer changes that will be discarded. Preserve submission semantics when experimenting with form layout or state management.

## Save response

The current save path leaves the form mounted and returns out-of-band updates for status, errors, publication, working-copy state, and preview. Replacing the form would lose unsaved client state. The coupled payload and targets live in [Editor](../src/Controller/Panel/Editor.php), [editor-save.php](../panel/views/editor-save.php), and the [save tests](../tests/End2End/PanelEditorSaveTest.php), not a second list of IDs here.

This is an internal boundary, not a plugin action-slot API. Changes to it need corresponding consumer updates rather than an assumption that the current IDs are permanent.

## Working copies

See [working copies](content.md#working-copies) for live/draft semantics and the Store API. The editor saves, previews, publishes, and discards through those transitions; publication and visibility are not interchangeable with ordinary content fields.

## Validation errors

Failed saves preserve the mounted form. Error-summary items carry Sire paths:

```html
<button type="button" data-error-path='["content","title","value","en"]'>
	Title (English) is required
</button>
```

[errors.ts](../panel/src/behaviors/errors.ts) resolves paths to form controls, falling back to containing element hosts for nested values. It marks fields and reveals hidden targets by switching locale, opening the relevant inspector/tab/dialog, or expanding an entry. Editing clears field marks; the summary remains until another save.

Current styling hooks include `.cms-field[data-invalid='true']`, `.cms-field-error`, and `.has-error`. Native controls receive accessible invalid/error associations. Custom elements currently receive field-level marking rather than a standardized per-sub-value error API.

## Blocks

[Blocks](blocks.md) documents types, translation, stored shape, form names, and frontend output. The editor shares the repeater machinery with Entries and adds layout, duplication, and a type catalog. Duplication copies live values, not the original server payload, while assigning a fresh UID; media duplication copies references, not files.

The current canvas is an editing surface rather than a rendering of the site. Its controls can use a content-first presentation through `field.presentation = 'block'` and a settings slot, described below. Layout changes update hidden form inputs; the dialog and resize handles apply the same bounds. See [keyboard alternatives](panel-keyboard.md#block-controls).

A press anywhere on a block's empty area puts the caret at the end of its text, as does a press on one of its edges that resizes nothing; a split's own area and the seams between its parts are not a part's. A blank rich text shows its field's placeholder on its empty line; the built-in Rich text block shows "Write…".

On a multi-column field every block sits at its stored position. Dragging a block by its grip lifts it out of the grid: it follows the pointer, a sunken slot of its size shows where it lands, and the blocks it would push move to where they would end up. The cell under the pointer is where the grabbed cell lands; dropping onto other blocks pushes them down below it, keeping their order, and the pointer on the line between two rows opens a new row there instead, moving everything below down as a whole. Near the top or bottom edge of the scrolling pane the drag scrolls it. Escape puts everything back. Resizing grows a block sideways only into free cells, up to the grid's edge; the start edge moves the block's first column and keeps its end, and a taller block pushes the blocks below it down. Rows no block covers any more are removed, and the form always submits the rows in reading order. Every change of positions animates from where the blocks were, unless the system asks for reduced motion.

The canvas shows its empty cells as ghost blocks: dotted boxes that each appear while the pointer is over their own cells or when they take focus. Clicking a ghost inserts a block that fills that gap exactly, so no other block moves. A field with one block type stamps it at once; otherwise the field's picker opens inside the gap, or beside it when the gap is too small, and the type chosen there or from the catalog lands in the gap. Gaps narrower than the field's minimum width get no ghost. A block added from a row's inserter or duplicated lands in new rows next to that row; the add bar adds below everything.

A block of a multi-column field splits into parts, side by side or stacked, from its action menu: "Split into columns" or "Split into rows" on a block of the grid, "Split" on a part, in its split's direction. A split halves the clicked block — the first one puts a split in the block's place with the block as its first part — and the new part's type comes from the field's picker, opened at the menu's trigger, or is the field's one type. The entries are disabled where there is no room: halves narrower than the field's minimum, or a split taller than six rows. The canvas shows a split as one bordered block with dashed seams between its parts: the split's tools sit at its top end corner as a block's do, each part's inside its bottom end corner. Blocks move between the grid and a split as they are, so an element control keeps its edits. Removing a part hands its space to its neighbour, the previous one or the next for the first, and a split left with one part turns back into that block with the split's layout. A split's parts follow its width in proportion and its height; a part's width trades with its neighbour through its settings, its grip or the seam between them, and a stacked part's rows set the split's height, which has no edge of its own. A split's row submits no type and its parts under `[blocks][j]` ([form names](blocks.md#editor-form-names)).

"Change block type", in a block's or a part's action menu when the field has more than one type, replaces the block with a fresh one of the type picked from the field's picker, opened at the menu's trigger. The new block takes the old one's position and size, or its place in the list or split, and its settings (class, ID, padding); its content does not carry over. A block holding any content asks for confirmation first: a typed value that differs from a fresh block's, or text or files in an element control. Picking the block's own type changes nothing.

Layout preview posts the current form without saving or validating, renders the selected field through its block types, and displays it with the reference stylesheet. It does not include site-specific fonts or styles. The preview iframe uses `sandbox="allow-same-origin"` without script permission; that restriction matters even though block output can contain trusted embed markup. Browser-side YouTube thumbnail replacement also contacts YouTube's image host. See [Editor](../src/Controller/Panel/Editor.php) and the panel behaviors for endpoint and preset details.

## Element controls

A field can select a one-off element with `Control::element('acme-picker', 'acme-shop/picker.js')`, or use `Control::named('acme-map')` registered by a plugin:

```php
$cms->control('acme-map', 'acme-map-picker', 'map.js');
```

Named controls resolve server-side; later registrations replace earlier ones, including built-ins. Hand-written ES modules are sufficient; Svelte is not required.

### Module values

| Form | Resolution |
| --- | --- |
| `{pluginId}/{file}` | Plugin asset directory; Registrar prefixes its ID automatically |
| `cosray:{entry}` | Built-in panel element, or Vite during development |
| `https?://...` | Used as supplied |

Modules load once through dynamic `import()` and register their element at module evaluation. Registration should tolerate a definition already existing.

### The element contract

The [host](../panel/src/lib/host.ts) assigns JavaScript properties, not attributes:

| Property | Meaning |
| --- | --- |
| `value` | Full stored value shape, usually a locale map |
| `meta` | Field metadata when present |
| `field` | Field properties; `immutable` signals read-only, `presentation: 'block'` requests the current content-first block presentation |
| `node` | Owner node UID, or an empty string during creation |
| `locale` | Selected editing locale; neutral controls still use `zxx` |
| `locales` | `{default, all: [{id, title, fallback?}]}` |
| `assets` | Catalog metadata keyed by asset UID, for resolving previews from `{uid, meta?}` values |
| `settings` | Optional external host for secondary controls, currently a block settings slot |

Values and locale properties can be reassigned; handle repeated assignments idempotently. A settings slot is assigned once by the current host; without one, secondary UI remains inline.

Report user edits using the full new value and optional meta:

```js
this.dispatchEvent(
	new CustomEvent("cosray-change", {
		detail: { value, meta },
		bubbles: true,
		composed: true,
	}),
);
```

Do not emit edits merely in response to host assignments: that creates feedback loops and can turn display-only changes into saved values. Read-only elements should retain usable reading/selection without offering mutation actions.

## Conditional fields

```php
#[When('multiDay')]
public Date $endDate;
```

`#[When]` supports truthiness, equality, membership, and explicit operators; see [Schema\When](../src/Schema/When.php) and the shared [condition fixtures](../contract/conditions.json).

Inactive controls are hidden without clearing their stored values. Reads present an inactive field as empty; `Field::raw()` bypasses that behavior. Editor condition sources currently need primitive, non-translated fields, and conditions are not emitted inside typed rows. `#[Required]` still validates on the server while a field is inactive, despite browser required state being suspended. These are current limitations, not restrictions on future scoped conditions.

## The window.Cosray bridge

The [bridge](../panel/src/lib/bridge.ts) exposes versioned services to element controls:

- `version`: currently `1`; check it before relying on the interface.
- `system()`: panel locale, content locales, paths, upload settings, and related runtime metadata.
- `upload(type, file)`: uploads to the asset pool with the session CSRF token.
- `modal.open(render, options?)`: mounts content and returns a `close()` handle.
- `toast.success(message)` and `toast.error(message)`.

Upload results include catalog identity and preview information; persist `{uid}` in the value, not the whole response. The bridge is installed from system payloads, so elements mounted outside a configured panel screen need to handle its absence.

### Modal controls

`modal.open()` supplies an empty host in a native dialog. Its renderer may return a cleanup function, run once on closure including navigation or owner removal. Options include `hideClose`, `label`, `size` (`compact` or `wide`), and `owner`. Supply an owner for asynchronous callers or controls whose removal is independent of the screen.

Escape and a gesture starting and ending on the backdrop dismiss the dialog; `hideClose` hides only the close button. Dismissal cancels confirmations. Nested dialogs return focus to the underlying dialog; a renderer failure must not leave a modal host behind. Close before an action that focuses new content so restoration cannot override it.

Current internal parts are `.modal-header`, `.modal-title`, `.modal-body`, and `.modal-footer`; `data-dialog-focus` selects initial focus. They are shared markup, not an additional plugin slot API. Server-rendered settings stay inside their editor form and retain live values on closure; media metadata dialogs keep their own draft and Apply/Cancel behavior.

[dialogs.ts](../panel/src/lib/dialogs.ts) and the [action-menu behavior](panel-styles.md#action-menus) coordinate focus and ownership. Browser `beforeunload`, dirty-navigation, and `hx-confirm` prompts remain separate synchronous paths.
