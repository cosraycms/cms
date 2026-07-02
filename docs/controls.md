# Editor control vocabulary

Every field type describes its editor UI as a **control descriptor** returned by the field's `control(): Cosray\Field\Control` method and serialized into the field payload as `control: { name, props }`. The editor island interprets only **primitive** controls (plain HTML inputs), the structural `group`/`repeater`, and `element`. Everything else — named rich controls, cosray's own included — resolves server-side through the control registry to an element descriptor and is rendered by a **custom element**. The island knows neither field type classes nor built-in control names.

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
| `group` | `Control::group(fields)` | `fields: {key,label?,control}[]` | `zxx` map of object keyed by `key` |
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

Named controls are resolved server-side to element descriptors before serialization; the editor island never sees the name. Later registrations win, so a plugin may replace a built-in editor by registering its name (e.g. `richtext`). Cosray's own rich controls are registered through the same registry and shipped as custom elements — they are the reference implementations.

### Module values

| Form | Served from |
| --- | --- |
| `{pluginId}/{file}` | the plugin's asset dir via `{panel}/vendor/{pluginId}/{file}` (`Registrar::control()` prefixes the plugin id automatically) |
| `cosray:{entry}` | the panel build (`{panel}/build/elements/{entry}.js`, dev server in development). `cosray` is a reserved plugin id. |
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

## The window.Cosray bridge

On panel editor pages the island installs `window.Cosray`, a versioned runtime API for element controls — cosray's own and plugin-shipped ones alike:

```ts
window.Cosray = {
	version: 1,
	system(): { locale, defaultLocale, locales, customLocales, prefix, assets, debug, allowedFiles },
	upload(type: 'image' | 'file' | 'video', node: string, file: File): Promise<{ok, file?, error?}>,
	modal: { open(render: (host: HTMLElement) => cleanup?, options?): { close() } },
	toast: { success(message), error(message) },
};
```

`upload()` posts to the media endpoint with the session's CSRF token — elements never handle credentials. `modal.open()` hands the callback an empty host element inside the panel's modal chrome; render arbitrary DOM into it and optionally return a cleanup function. The bridge only exists while an editor is mounted — elements used elsewhere should degrade or show a hint. Check `window.Cosray?.version === 1` before relying on it.
