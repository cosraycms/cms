# Editor control vocabulary

Every field type describes its editor UI as a **control descriptor** returned by the field's `control(): Cosray\Field\Control` method and serialized into the field payload as `control: { name, props }`. The editor renders **primitive** controls as server-side Boiler views (plain HTML inputs) and the structural `group`/`repeater` the same way. Everything else — named rich controls, cosray's own included — resolves server-side through the control registry to an element descriptor and is rendered by a **custom element** hosted in a form-associated `<cosray-host>` that carries the value into the form submission as one JSON leaf. The panel knows neither field type classes nor built-in control names.

Cross-cutting concerns are **not** part of the descriptor. Label, locale tabs, required marker, description, and width come from the field's other properties (driven by schema attributes such as `#[Label]`, `#[Required]`, `#[Translate]`, `#[Width]`) and are rendered by the shared field wrapper.

## Value shapes

Field values are persisted as locale maps. The neutral locale key is `zxx`; translatable fields (`#[Translate]`) use real locale ids (`de`, `en`, ...). The table lists the shape of `value` per control name.

| Control | Builder | Props | Value shape |
| --- | --- | --- | --- |
| `text` | `Control::text(?placeholder)` | `placeholder?` | locale map of `string` |
| `textarea` | `Control::textarea()` |  | locale map of `string` |
| `number` | `Control::number(step:,min:,max:)` | `step?`, `min?`, `max?` | locale map of `number\|string` |
| `checkbox` | `Control::checkbox()` |  | locale map of `bool` |
| `option` | `Control::option(display:)` | `display: select\|radio` | locale map of `string` (options come from `#[Options]`) |
| `date` | `Control::date()` |  | locale map of `YYYY-MM-DD` |
| `time` | `Control::time()` |  | locale map of `HH:MM` |
| `datetime` | `Control::datetime()` |  | locale map of `YYYY-MM-DDTHH:MM` |
| `hidden` | `Control::hidden()` |  | locale map of `string` |
| `iframe` | `Control::iframe()` |  | locale map of `string` |
| `group` | `Control::group(fields)` | `fields: {key,label?,control,width?}[]` | `zxx` map of object keyed by `key` |
| `repeater` | `Control::repeater(item,min:,max:)` | `item`, `min?`, `max?` | `zxx` map of list of item values |
| `element` | `Control::element(tag, module)` | `tag`, `module` | whatever the field's `structure()` defines |

Named rich controls (resolved to elements server-side; cosray's built-ins ship as custom elements under `cosray:` modules):

| Control | Builder | Element | Value shape |
| --- | --- | --- | --- |
| `code` | `Control::code()` | `cosray-code` | locale map of `string`, `meta.syntax` (syntaxes from `#[Syntax]`) |
| `richtext` | `Control::richtext()` | `cosray-richtext` | locale map of HTML `string` |
| `image` | `Control::image()` | `cosray-image` | locale map of `{file, meta?}[]` |
| `file` | `Control::file()` | `cosray-file` | locale map of `{file, meta?}[]` |
| `video` | `Control::video()` | `cosray-video` | locale map of `{file, meta?}[]` |
| `blocks` | `Control::blocks()` | `cosray-blocks` | locale map of block list (see Blocks) |
| `entries` | `Control::entries()` | `cosray-entries` | `zxx` map of `{uid, type, fields}[]` |
| _custom_ | `Control::named('acme-map')` | via `Registrar::control()` | whatever the field's `structure()` defines |

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

The field wrapper then shows a "Meta" button opening a per-field dialog; entries submit as `content[{field}][meta][{key}][zxx]` through the merge patch — meta keys the group does not know survive untouched. Element controls keep managing their meta themselves (through the `cosray-change` detail); `metaControl()` is for native fields.

## Derived inputs

Two declarative helpers for field templates, evaluated on every form edit:

- `data-derive="title"` on an input mirrors the named sibling field's value, optionally through `data-derive-transform="slugify"`. The input detaches the moment it is edited manually (or starts detached when its stored value already differs).
- `<output data-count-of="field-title-zxx">` renders the character count of the referenced input.

Anything richer than cross-field mirroring belongs in an `element` control, not in new behaviors.

## Block types

Block types inside a `blocks` field are pluggable through the same mechanism. A block type extends `Cosray\Block\Type` and provides `id()`, `label()`, `control()`, `init()` (the payload created when the editor adds the block) and `render(Block, RenderContext)` (frontend HTML). Plugins register types via `Registrar::blockType(MyBlock::class)`; a `Blocks` field restricts its offered types with `#[Allows('richtext', 'my-block')]`. The block natives (`block-text`, `block-richtext`, `block-image`, `block-images`, `block-youtube`, `block-video`, `block-iframe`) are rendered internally by the `cosray-blocks` element; a plugin block type uses an `element` control, and its web component gets the contract below with `block` (`{type, index}`) assigned additionally.

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
| `cosray:{entry}` | the panel static assets (`{panel}/static/elements/{entry}.js`, dev server in development). `cosray` is a reserved plugin id. |
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
  - `field` — the full field properties object (`name`, `label`, `required`, `translate`, `options`, ...).
  - `node` — the node uid; `''` while creating a node that has not been saved yet.
  - `locale` — the **currently selected editing locale**. The field wrapper owns the locale tabs; when `field.translate` is true this property changes as the editor switches tabs — render `value[locale]`.
  - `locales` — `{ default: string, all: {id, title}[] }`.
  - `assets` — resolved catalog data for every asset uid the entry references: `{ [uid]: { filename, url, kind, mime?, width?, height?, meta? } }`. Media items in `value` are `{uid, meta?}`; previews resolve uids through this map. Upload responses carry the same data for freshly added assets.
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

Limitations (v1): condition sources must be primitive, non-translated fields (checkbox, option, text, number); conditions inside repeaters are not evaluated; combining `#[When]` with `#[Required]` still enforces required on save while the field is inactive.

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

`upload()` posts to the pool endpoint `POST /media/{type}` with the session's CSRF token — elements never handle credentials. It returns the catalog asset (`uid`, `url`, `filename`, ...); store `{uid}` in the field value and keep the rest for previews. `GET /media/library` lists the catalog for reuse pickers (`kind`, `q`, `page` parameters). `modal.open()` hands the callback an empty host element inside the panel's modal chrome; render arbitrary DOM into it and optionally return a cleanup function. The bridge only exists on editor pages — elements used elsewhere should degrade or show a hint. Check `window.Cosray?.version === 1` before relying on it.
