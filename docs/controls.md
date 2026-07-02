# Editor control vocabulary

Every field type describes its editor UI as a **control descriptor** returned by the field's `control(): Cosray\Field\Control` method and serialized into the field payload as `control: { name, props }`. The panel renders fields through one generic dispatcher; it has no knowledge of field type classes. Plugins that need UI beyond the vocabulary use the `element` control.

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
| `code` | `Control::code()` |  | locale map of `string` (syntaxes come from `#[Syntax]`) |
| `richtext` | `Control::richtext()` |  | locale map of HTML `string` |
| `iframe` | `Control::iframe()` |  | locale map of `string` |
| `image` | `Control::image()` |  | locale map of `{file, meta?}[]` |
| `file` | `Control::file()` |  | locale map of `{file, meta?}[]` |
| `video` | `Control::video()` |  | locale map of `{file, meta?}[]` |
| `blocks` | `Control::blocks()` |  | locale map of block list (see Blocks) |
| `entries` | `Control::entries()` |  | `zxx` map of `{uid, type, fields}[]` |
| `group` | `Control::group(fields)` | `fields: {key,label?,control}[]` | `zxx` map of object keyed by `key` |
| `repeater` | `Control::repeater(item,min:,max:)` | `item`, `min?`, `max?` | `zxx` map of list of item values |
| `element` | `Control::element(tag, module)` | `tag`, `module` | whatever the field's `structure()` defines |

Limitations (v1): `group` and `repeater` support only primitive sub-controls (`text`, `textarea`, `number`, `checkbox`, `option`, `date`, `time`, `datetime`, `hidden`) and neutral-locale values.

## Block types

Block types inside a `blocks` field are pluggable through the same mechanism. A block type extends `Cosray\Block\Type` and provides `id()`, `label()`, `control()` (same vocabulary, plus the block natives `block-text`, `block-richtext`, `block-image`, `block-images`, `block-youtube`, `block-video`, `block-iframe`), `init()` (the payload created when the editor adds the block) and `render(Block, RenderContext)` (frontend HTML). Plugins register types via `Registrar::blockType(MyBlock::class)`; a `Blocks` field restricts its offered types with `#[Allows('richtext', 'my-block')]`. A block type whose control is `element` renders a plugin web component in the editor — the same contract as below, with `block` (`{type, index}`) assigned additionally.

## The element escape hatch

A field that needs a custom UI declares:

```php
public function control(): Control
{
    return Control::element('acme-color-picker', 'acme-shop/controls.js');
}
```

The module path is `{pluginId}/{file}` relative to the directory the plugin registered via `Registrar::assets()`; it is served from `{panel}/vendor/{pluginId}/{file}` and loaded once via dynamic `import()`. The module must define the custom element at top level:

```js
customElements.get('acme-color-picker') ||
    customElements.define('acme-color-picker', class extends HTMLElement { ... });
```

Hand-written ES modules are sufficient — no build step required. The contract between the panel and the element:

- The panel assigns JS **properties** (not attributes) on the element:
  - `value` — the stored value in the exact shape the field's `structure()` persists (usually a locale map). Treat repeated assignments as idempotent.
  - `field` — the full field properties object (`name`, `label`, `required`, `translate`, `options`, ...).
  - `locale` — the currently active panel locale id.
  - `locales` — `{ default: string, all: {id, title}[] }`.
- The element reports every edit by dispatching a composed, bubbling custom event with the **full new value** in the same shape:

  ```js
  this.dispatchEvent(
  	new CustomEvent("cosray-change", {
  		detail: { value },
  		bubbles: true,
  		composed: true,
  	}),
  );
  ```

- When `field.translate` is true, locale handling is the element's responsibility: keep one value per locale id in the map and use `locale`/`locales` to decide what to show.
