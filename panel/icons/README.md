# Built-in panel icons

Most of these SVG assets are an unmodified subset of [Bootstrap Icons 1.13.1](https://github.com/twbs/icons/tree/v1.13.1/icons), licensed under MIT. Copyright (c) 2019–2024 The Bootstrap Authors. See `LICENSES/MIT.txt` and the file-level attribution in `REUSE.toml` at the repository root.

Draw a new icon, or take one from another set with a compatible license, whenever it says something more clearly than the closest existing one. Build on established symbols rather than replacing them: a trash can still deletes, a slash still negates. Keep the 16px grid and the regular line weight, record the origin in `REUSE.toml`, and note custom artwork below. Both `Cosray\Panel\Icon::render()` and `icon()` in `src/lib/icons.js`, through the served sprite, read this collection. Their name argument is a canonical asset name, not a path or arbitrary SVG. An icon carries the class `cms-icon` and none of the artwork's own; where a rule must single out one icon, `Icon::render()` takes a part class of the surrounding component as a second argument. Icons are decorative; name icon-only actions on their buttons or links.

Notable mappings from the former artwork:

- The old PHP `plus.svg` becomes `plus-circle`; plain add actions use `plus`.
- Kebab and overflow actions use `three-dots-vertical`.
- Close actions use `x-lg`; save actions use `floppy`, preview uses `eye`, and delete uses `trash3`.
- Links that open the website in a new tab use `box-arrow-up-right`.
- Filled status symbols become `info-circle`, `shield-check`, `x-octagon`, and `exclamation-triangle`.
- Font Awesome link/text-height artwork becomes `link-45deg` and `type`; unlink uses `slash-circle`.
- Text style uses `fonts`, document uses `file-earmark-richtext`, and the collection fallback uses `collection`.
- Media fields use `folder2-open` for the library and the `file-earmark-*` family — `pdf`, `word`, `excel`, `ppt`, `zip`, `music`, `play`, `text`, `image`, plain `file-earmark` as the fallback — for files without a thumbnail. An image's details use `copy` for its address.

Masthead areas use `grid-1x2` for the dashboard, `files` for content, `image` for media, `list-nested` for menus, and `people` for users.

A full-row grid blocks field toggles its width with `arrows-expand-vertical` and `arrows-collapse-vertical`.

The node inspector uses `layout-sidebar-inset-reverse` to collapse and expand, and its strip uses `toggles`, `link-45deg`, and `sliders` for the status, paths, and advanced tabs. Its status tab, the editor head and lists mark the stages from `Cosray\Panel\NodeStatus::icon()`: the main version and a record's saved state by their state, `check-lg` while online or saved and `slash-circle` while offline, and what waits by what it is, the draft with `file-earmark-text` and the user's own edits with `pencil`; its steps use `arrow-up` on the line and `arrow-bar-up` for applying, which the head's Apply shares, and `lightning` says the visibility switches act at once.

The layout preview's width presets use `display`, `tablet-landscape`, `tablet`, and `phone`; its reload uses `arrow-clockwise`.

Block defaults map RichText → `body-text`, Heading → `heading`, Image → `image`, Images → `images`, Video → `film`, Youtube → `play-btn`, and Iframe → `window`. Custom blocks without available artwork use `square`. These defaults are bundled and work without an Iconify request, including on a cold cache.

The custom `heading` icon is inspired by Bootstrap's `card-heading`, without the card border and with an additional thin text line. Level-specific rich-text actions retain `type-h1`, `type-h2`, and `type-h3`.

Bundled defaults use a separate `panelIcon` name when passed as data. Custom schema `icon` metadata remains `{id, args}` and resolves through the existing icon providers, including existing `bi:*` IDs. A bundled default is not a provider ID.

The language select on the sign-in pages uses `translate`.
