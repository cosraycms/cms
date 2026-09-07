# Built-in panel icons

These unmodified SVG assets are a subset of [Bootstrap Icons 1.13.1](https://github.com/twbs/icons/tree/v1.13.1/icons), licensed under MIT. Copyright (c) 2019–2024 The Bootstrap Authors. See `LICENSES/MIT.txt` and the file-level attribution in `REUSE.toml` at the repository root.

Use regular variants, never `*-fill` artwork. Both `Cosray\Panel\Icon::render()` and the Svelte `Icon` component read this collection. Their only argument is a canonical asset name, not a path or arbitrary SVG. Icons are decorative; name icon-only actions on their buttons or links.

Notable mappings from the former artwork:

- The old PHP `plus.svg` becomes `plus-circle`; plain add actions use `plus`.
- Kebab and overflow actions use `three-dots-vertical`.
- Close actions use `x-lg`.
- Filled status symbols become `info-circle`, `shield-check`, `x-octagon`, and `exclamation-triangle`.
- Font Awesome link/text-height artwork becomes `link-45deg` and `type`; unlink uses `slash-circle`.
- Text style uses `fonts`, document uses `file-earmark-richtext`, and the collection fallback uses `collection`.

Bundled defaults use a separate `panelIcon` name when passed as data. Custom schema `icon` metadata remains `{id, args}` and resolves through the existing icon providers, including existing `bi:*` IDs. A bundled default is not a provider ID.
