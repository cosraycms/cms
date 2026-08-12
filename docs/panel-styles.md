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

Two rules make the palette work:

- **Pairs flip together.** `--cms-color-primary` and `--cms-color-primary-text` are one decision. Overriding the background alone produces an unreadable button in one of the two themes.
- **Mix against tokens, not literals.** `color-mix(…, var(--cms-color-surface) 88%)` survives the dark flip; `color-mix(…, white 88%)` glows on a dark canvas.

### Colour roles

Primary is monochrome — near-black on light, near-white on dark. The chrome stays neutral so a project's own colour can be the accent instead of fighting it.

Accent covers links, focus rings, selection and active navigation, and is the token projects are expected to tint. Focus is accent-based on purpose: a neutral ring on a near-black primary button is invisible.

### Dark theme

Dark lives under `:root[data-theme='dark']` and is opt-in. The `prefers-color-scheme` switch lands once every screen is ported and checked — enabling it earlier would hand a half-dark panel to everyone whose system is set to dark.

`color-scheme` is declared in `tokens.css` alongside the palette so native controls, scrollbars and form widgets follow. Do not redeclare it from a component stylesheet: the `panel` layer outranks `tokens` and would pin the whole panel to one scheme.

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
5. **One block per file**, named after the block.
6. **Variation goes through a local custom property on the block**, not through extra selectors. `--columns` above is the pattern: adding a column later is a value change, not a rewrite. Block-local properties stay unprefixed — they are scoped to one component and are not part of the theming contract.

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

## Migration

The panel is mid-redesign. Older stylesheets still use unprefixed class names and a flatter structure. They are converted per screen, not in one sweep: the old file is deleted when its screen lands. Both conventions coexist inside the `panel` layer until then.

New CSS follows the convention above, without exception.
