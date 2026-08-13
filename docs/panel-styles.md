# Panel styles

How CSS is organised in `panel/styles/` and in the `<style>` block of panel Svelte components: cascade layers, the design tokens, and the class naming convention.

## Cascade layers

`panel/views/base.php` declares the order before any stylesheet loads:

```css
@layer tokens, reset, panel, plugin, theme;
```

| Layer    | Holds                                                         |
| -------- | ------------------------------------------------------------- |
| `tokens` | `tokens.css` only — design tokens, nothing else               |
| `reset`  | `reset.css`                                                   |
| `panel`  | every panel stylesheet                                        |
| `plugin` | CSS injected by plugins through `Cosray\Panel\Extras`         |
| `theme`  | stylesheets a project lists under `panel.theme` in its config |

Layer order beats specificity, so a downstream project restyles the panel by redeclaring a token or a rule in `@layer theme` — no `!important`, no selector arms race. The same holds for plugins, one layer down.

## Tokens

`panel/styles/tokens.css` is the only file that may contain a raw colour value. Every colour has to flip for the dark theme, and a hex buried in a component stylesheet is a hole in that flip.

Three tiers:

1. **Primitives** — raw values with no meaning attached: `--cms-color-neutral-300`, `--cms-space-4`, `--cms-radius-md`, `--cms-shadow-sm`. Panel-internal. Projects should not depend on them.
2. **Semantic** — what a value is _for_: `--cms-color-surface`, `--cms-color-text-muted`, `--cms-color-primary`, `--cms-color-danger-surface`. This tier is the public theming contract.
3. **Component** — owned by one component: `--cms-button-primary-bg`, `--cms-sidebar-width`, `--cms-inspector-width`.

### The theming contract

Downstream projects override semantic and component tokens. Primitives are internal and may be renamed or re-tinted without notice.

```css
/* project stylesheet listed under panel.theme */
@layer theme {
	:root {
		--cms-color-accent: #1f3f72;
		--cms-sidebar-width: 18rem;
	}
}
```

Three rules make the palette work:

- **Components reference semantic tokens, never primitives.** `--cms-color-white` and `--cms-color-neutral-900` do not flip — that is the point of a primitive. A component that reaches for one is pinned to the light theme even though it contains no raw hex. Use `--cms-color-surface` and `--cms-color-text`. The exceptions are values that genuinely must not flip: a scrim that stays dark in both themes, or an iframe showing site content rather than panel chrome.

- **Pairs flip together.** `--cms-color-primary` and `--cms-color-primary-text` are one decision. Overriding the background alone produces an unreadable button in one of the two themes.
- **Mix against tokens, not literals.** `color-mix(…, var(--cms-color-surface) 88%)` survives the dark flip; `color-mix(…, white 88%)` glows on a dark canvas. The same holds for the direction a variant moves in: mix toward `--cms-color-shade`, which is black on light and white on dark, so a hover darkens on one theme and lightens on the other instead of sinking into the surface.

An override is a plain value and applies to both themes. A project that wants two, and it usually does not, writes `light-dark()` itself.

### Colour roles

Primary is monochrome — near-black on light, near-white on dark. The chrome stays neutral so a project's own colour can be the accent instead of fighting it.

Accent covers links, focus rings, selection and active navigation, and is the token projects are expected to tint. Focus is accent-based on purpose: a neutral ring on a near-black primary button is invisible.

### Dark theme

The panel follows the operating system. There is no dark block: both themes live in the one `:root` block, and a token that differs carries `light-dark(light, dark)` so its two values sit on the same line and cannot be changed in one theme and forgotten in the other.

`color-scheme: light dark` in `tokens.css` is what selects between them — `light-dark()` resolves against the used colour scheme — and it hands native controls, scrollbars and the canvas the matching defaults at the same time. Do not redeclare `color-scheme` from a component stylesheet: the `panel` layer outranks `tokens`, so a stray declaration pins every token below it to one theme. That is a whole-panel switch wearing the costume of a local one.

`data-theme="light"` or `data-theme="dark"` on `<html>` forces a theme, and because the palette resolves through `light-dark()` the attribute only has to set `color-scheme`. Nothing sets it today except the styleguide's toggle; it is the hook a per-user theme preference would use.

## Class names

Prefix the block root with `cms-`. Everything inside is a plain noun, nested, and never referenced from outside its block.

```css
.cms-list {
	--columns: minmax(0, 1fr) 8rem 10rem 7rem;

	display: grid;
	grid-template-columns: var(--columns);

	& .row {
		display: grid;
		grid-column: 1 / -1;
		grid-template-columns: subgrid;

		&:hover {
			background: var(--cms-color-hover);
		}

		&.is-selected {
			background: var(--cms-color-selected);
		}
	}

	& .title {
		min-width: 0;

		& a:hover {
			text-decoration: underline;
		}
	}

	& thead th {
		color: var(--cms-color-text-subtle);
	}
}
```

The rules:

1. **Only the block root carries the prefix** — `.cms-list`, `.cms-shell`, `.cms-node`, `.cms-fields`. The panel loads plugin and project CSS into the same document, and the prefix is what keeps those apart.
2. **Element selectors are fine inside a block.** We own the markup. `& thead th` beats inventing a class for every node. Reach for a class when the thing varies, carries state, or JavaScript looks it up.
3. **States are `is-` / `has-`, attached with `&`** — `.is-active`, `.is-open`, `.has-children`. Never written as standalone selectors.
4. **Two structural levels, maximum.** Chain `&` for states rather than descending further. Deep nesting inflates specificity and makes overrides inside the panel layer painful.
5. **Blocks that host other blocks need distinctive part names.** A page-level block wraps whatever screens put inside it, so a part called `.row` or `.item` there will also match the rows of a list nested within — plain nesting scopes a part to its block's subtree, not to the block itself. Name such parts for what they are in that block (`.sample`, `.pane`), or scope them with `@scope`.
6. **One block per file**, named after the block.
7. **Variation goes through a local custom property on the block**, not through extra selectors. `--columns` above is the pattern: adding a column later is a value change, not a rewrite. Watch the cascade when a view sets one inline — an inline custom property outranks every stylesheet, so a media query has to override the property it feeds rather than the custom property. Block-local properties stay unprefixed — they are scoped to one component and are not part of the theming contract.

### Blocks that nest into themselves

Fields inside repeater fields, nodes inside a tree. Plain nesting cannot stop an outer block from styling an inner one; `@scope` can:

```css
@scope (.cms-fields) to (.cms-fields) {
	.label {
		/* applies to this block's own labels, not a nested block's */
	}
}
```

Use it only where a block genuinely contains itself. Nesting handles everything else.

## Styleguide

`/<panel-path>/styleguide` renders every component against the current stylesheets — tokens, buttons, pills, status, form controls, fields, empty states — plus a theme toggle. It is registered only when `app.debug` is on, and sits behind the same authentication as the rest of the panel.

It exists because the states that break quietly are the ones real content rarely produces: empty, disabled, error, a title long enough to truncate, a node with four locale paths in the inspector. Checking those, and checking dark, should not mean hunting for content that happens to trigger them.

Two rules keep it honest:

- **Render partials, never copies.** Fields come from `panel/views/field/*` with fixture data. A styleguide with its own copy of the markup drifts, and a stale styleguide is worse than none. Where a screen has no extractable partial yet, its section is inline and marked, and it is replaced when that screen is ported.
- **Read tokens from the stylesheet.** The token tables are parsed out of `tokens.css` at request time, so the palette cannot drift from what is documented.

Adding a component means adding it here too.

## Migration

The panel is mid-redesign. Older stylesheets still use unprefixed class names and a flatter structure. They are converted per screen, not in one sweep: the old file is deleted when its screen lands. Both conventions coexist inside the `panel` layer until then.

New CSS follows the convention above, without exception.
