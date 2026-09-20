# Panel styles

How CSS is organised in `panel/styles/` and in the `<style>` block of panel Svelte components: cascade layers, the design tokens, and the class naming convention.

This file holds the rules. It deliberately does not describe what the panel currently looks like: `tokens.css` is the token list, and the styleguide at `/<panel-path>/styleguide` renders every component against the live stylesheets. Prose that mirrors CSS goes stale on the next restyle and nothing lints it.

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
2. **Semantic** — what a value is _for_: `--cms-font-family`, `--cms-color-surface`, `--cms-color-text-muted`, `--cms-color-accent`, `--cms-color-danger-surface`. This tier is the public theming contract.
3. **Component** — owned by one component: `--cms-button-primary-bg`, `--cms-sidebar-width`, `--cms-inspector-width`.

A `/**` comment block in `tokens.css` names a group and the styleguide parses those groups out of the file at request time, so a new token appears there by being declared.

### The theming contract

Downstream projects override semantic and component tokens. Primitives are internal and may be renamed or re-tinted without notice.

```css
/* project stylesheet listed under panel.theme */
@layer theme {
	:root {
		--cms-color-accent: #1f3f72;
		--cms-sidebar-width: 20rem;
	}
}
```

Three rules make the palette work:

- **Components reference semantic tokens, never primitives.** `--cms-color-white` and `--cms-color-neutral-900` do not flip — that is the point of a primitive. A component that reaches for one is pinned to the light theme even though it contains no raw hex. Use `--cms-color-surface` and `--cms-color-text`. The exceptions are values that genuinely must not flip: a scrim that stays dark in both themes, an iframe showing site content rather than panel chrome, or a marker drawn over arbitrary media. Code boxes stay dark in both themes too, but through `--cms-code-bg`, `--cms-code-text` and `--cms-code-muted`, so a theme can restyle them.
- **Pairs flip together.** `--cms-color-accent` and `--cms-color-text-on-accent` are one decision. Overriding the background alone produces an unreadable button in one of the two themes.
- **Mix against tokens, not literals.** `color-mix(…, var(--cms-color-surface) 88%)` survives the dark flip; `color-mix(…, white 88%)` glows on a dark canvas. The same holds for the direction a variant moves in: mix toward `--cms-color-shade`, which is black on light and white on dark, so a hover darkens on one theme and lightens on the other instead of sinking into the surface.

An override is a plain value and applies to both themes. A project that wants two, and it usually does not, writes `light-dark()` itself.

### Typography

`--cms-font-family` is the public panel font-family token. Self-host a project's font files, declare their faces outside the layer, and override the family inside it:

```css
@font-face {
	font-family: "Example Sans";
	font-style: normal;
	font-display: swap;
	font-weight: 100 900;
	src: url("/fonts/example-sans.woff2") format("woff2-variations");
}

@layer theme {
	:root {
		--cms-font-family: "Example Sans", ui-sans-serif, system-ui, sans-serif;
	}
}
```

Font-size and line-height tokens form the panel's internal type scale and are not part of the theming contract. Form labels are public through `--cms-color-text-label`, and the `(required)` marker through `--cms-color-text-requirement`.

### Colour roles

Accent is the one interaction colour: primary buttons, focus, selection, active navigation and links. It defaults to a neutral so the chrome stays black, white and grey, and it is the pair projects are expected to tint — `--cms-color-accent` with `--cms-color-text-on-accent`. Hovers mix the accent toward the surface, which works for a dark accent and a tinted one alike. A link set in the accent carries an underline, since a dark grey link is otherwise just text.

Focus is a solid ring in the accent. Borderless elements take `outline: var(--cms-focus-outline)` with `outline-offset: var(--cms-focus-offset)`, and the gap keeps the ring visible around a filled button of the same colour. Bordered fields switch their border to `--cms-color-focus` and add `box-shadow: var(--cms-focus-ring)`, which thickens it.

Status colours are the only hues in the default chrome: red for danger and errors, amber for warnings, green for success and blue for information. Each `--cms-color-{status}` passes 4.5:1 as text and as a fill behind `--cms-color-text-on-fill` in both themes. `--cms-color-danger-border` clears 3:1 only, so it marks invalid controls and error boxes and never colours text.

Form controls draw their edge with `--cms-color-border-control`, which exists so `prefers-contrast: more` and a theme can firm up control edges without adding weight to sections, rows and cards. Keep it off anything that is not a control. A control without a visible label needs another cue, since WCAG 1.4.11 asks for a 3:1 boundary when the border is the only one. A read-only control keeps full contrast, because the value is there to be read and copied; a disabled one is exempt under WCAG 1.4.3.

Every text input, select and textarea in the panel carries `.cms-input`, `.cms-select` or `.cms-textarea`, in PHP views and Svelte components alike, and those classes are the only place a control's border, fill, depth, focus and states are drawn. A component may size and place a control, never redraw it. There is no element-level control look: a bare `input` renders as the browser draws it, which makes a missing class obvious. Controls are `--cms-control-height` tall and derive their vertical padding from it, so a theme changes form density once; give a control a width and inline padding, never a height of its own.

### Depth

Light falls from above. Four public tokens carry it. They are translucent white and shadow laid over whatever sits beneath, not colours, so neither theme needs its own value: a shadow disappears on a dark ground and a sheen on a white one.

| Token | Draws |
| --- | --- |
| `--cms-shadow-raised` | a lip below the bottom edge and a faint sheen along the top |
| `--cms-shadow-raised-fill` | the same lip and a bright sheen along the top |
| `--cms-shadow-recessed` | shading inside the top edge |
| `--cms-gradient-raised` | a lighter top than bottom, over the `background-color` |
| `--cms-shadow-card` | a lip and a small spread below a card lying on the pane |
| `--cms-shadow-frame` | the shell frame's shadow on the canvas, where the frame floats |
| `--cms-pane-shadow` | shading inside a pane's edges |

A theme that wants a flat panel sets all seven to `none`; the first four flatten the controls alone. Borders remain the edge of every control: forced colours drop shadows and gradients, and `prefers-contrast: more` firms borders, not depth.

Two rules keep depth from going wrong. The gradient belongs in `background-image`, so a hover can still change the `background-color` beneath it. And a context that strips a control's border and fill, such as a bare block, sets `box-shadow: none` too, or the lip floats under nothing.

### Dark theme

The panel follows the operating system. There is no dark block: both themes live in the one `:root` block, and a token that differs carries `light-dark(light, dark)` so its two values sit on the same line and cannot be changed in one theme and forgotten in the other.

`color-scheme: light dark` in `tokens.css` is what selects between them — `light-dark()` resolves against the used colour scheme — and it hands native controls, scrollbars and the canvas the matching defaults at the same time. Do not redeclare `color-scheme` from a component stylesheet: the `panel` layer outranks `tokens`, so a stray declaration pins every token below it to one theme. That is a whole-panel switch wearing the costume of a local one.

`data-theme="light"` or `data-theme="dark"` on `<html>` forces a theme, and because the palette resolves through `light-dark()` the attribute only has to set `color-scheme`. Nothing sets it today except the styleguide's toggle; it is the hook a per-user theme preference would use.

#### Allowing only one theme

A project that does not want a dark panel declares the scheme it allows:

```css
/* project stylesheet listed under panel.theme */
@layer theme {
	:root {
		color-scheme: light;
	}
}
```

This is the same declaration a component stylesheet must not make, and it is fine here for the reason it is dangerous there: it is a whole-panel switch, and `:root` in a project theme is the one place that is the intent. `dark` gives the mirror case.

Note what it overrules. `theme` is the highest layer and layer order beats specificity, so this plain `:root` also outranks the panel's `:root[data-theme='dark']` — the styleguide toggle stops responding, and a per-user theme preference would be overruled too. It bans a theme rather than changing the default, which is usually what is wanted, but it settles the question above the editor's head.

### More contrast

`tokens.css` answers `prefers-contrast: more`, the operating system's increase-contrast setting, by darkening the border tokens, `--cms-color-border-control` included, and the muted, subtle and faint text colours. Everyone else keeps the calm default. The boost lives in the `tokens` layer, so a project that overrides one of these tokens in `@layer theme` replaces the boost for that token too and should repeat its value inside its own `@media (prefers-contrast: more)` block.

### Forced colours

Windows contrast themes (`forced-colors: active`) replace every colour with a small system palette, drop box-shadows and flatten backgrounds. Two things follow for panel CSS:

- **Focus must survive without colour and shadow.** A field that shows focus through its border colour and `--cms-focus-ring` resets the outline to `1px solid transparent`, never `none`. Forced colours paint a transparent outline, so it becomes the focus indicator there and stays invisible everywhere else.
- **A state carried by a background alone disappears.** The switch's position and the chosen content language are such states. The component opts out with `forced-color-adjust: none` inside `@media (forced-colors: active)` and redraws the state with system colours: `Canvas`, `ButtonText`, `Highlight`, `HighlightText`, `GrayText`. System colour keywords are the one colour value allowed outside `tokens.css`, since they are the palette the user chose.

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

## Shell frame

The shell is a white frame on the canvas. The sidebar, the page head and a rail beside the content share that one ground, `--cms-color-surface`, the sidebar through `--cms-color-rail` and the node inspector through `--cms-inspector-bg`, and no line separates them. Each screen's content area is a pane inside the frame, drawn from one set of tokens:

| Token | Is |
| --- | --- |
| `--cms-pane-radius`, `--cms-pane-radius-start`, `--cms-pane-radius-end` | its top corners |
| `--cms-pane-border`, `--cms-pane-border-start`, `--cms-pane-border-end` | its top and side edges |
| `--cms-pane-gap-end` | its distance from the frame's end edge, `0` by default |
| `--cms-pane-shadow` | shading inside its edges |

The fill is the screen's own decision, not part of the set: `--cms-color-surface` where the pane is the page itself, `--cms-pane-bg` where it is a ground for items laid on it.

A pane meets a white column on a side, or it meets the frame's own edge. A curve there leaves a white tip standing alone against the canvas and a border doubles the frame's, so `cms-shell.css` zeroes both the `-start` and `-end` tokens on the side that has no column beside it — no rail in the frame, no inspector in the main region — and zeroes both below the smallest band, where nothing sits beside anything. Above 120rem the frame floats on the canvas, and a pane without an inspector stands off the frame's end edge instead: the shell sets `--cms-pane-gap-end` there and hands the end curve and border back. A pane therefore writes the same three border declarations, the two radius ones and `margin-inline-end: var(--cms-pane-gap-end)` wherever it is, and never a bottom border: it runs off the bottom of the viewport. A pane whose content paints over its ground, such as a list that fills it, draws `--cms-pane-shadow` from an `::after` overlay with `pointer-events: none` instead of its own `box-shadow`, or the content covers the shade.

## Shell scrolling

The shell is exactly one viewport tall and never scrolls; the regions inside it do. A screen is a column of fixed rows around exactly one scrolling region, and a screen with an inspector is that column beside a second one. Every fixed row is `flex: 0 0 auto`, every scroller is `flex: 1 1 auto; min-height: 0`, and each flex ancestor of a scroller needs that `min-height: 0` as well or the scroller grows instead of scrolling. Scroll regions carry `overscroll-behavior: contain`. `position: sticky` is used only where a part sticks inside its own scroll region. Below 40rem in width or 30rem in height the shell hands scrolling back to the document, because nested scrollers and an on-screen keyboard do not get along.

The node and user editors separate the stationary `.pane` frame from its `.pane-scroll` child. The frame owns the pane tokens; the child owns scrolling, horizontal and bottom padding, and the `content` container query. In the document-scrolling band the child has ordinary content padding and visible overflow.

Every scroll region holds focusable content — links, inputs, checkboxes — so tabbing reaches it and the arrow keys and Page Down work from there. None of them carries `tabindex`, which would only add an empty tab stop ahead of the first link. A scroll region built without focusable content would need one.

After a navigation swap the new page brings a fresh scroll region, so nothing has to be reset. `behaviors/scroll.ts` exists for one case that survives a swap in the other direction: a collection tree toggle re-renders the list, and the behaviour carries the list's position across.

## Breakpoints

Three bands, and they are the only viewport-width queries the panel should contain:

| Band | Shell | Rail | Inspector |
| --- | --- | --- | --- |
| `width >= 75rem` | bounded | docked, 13rem | docked, 19rem |
| `40rem <= width < 75rem` | bounded | docked, 13rem | collapsed to its strip, drawer slides over the content |
| `width < 40rem` or `height < 30rem` | none, the document scrolls | a strip above the content | full width, above the content |

The boundaries sit in the gaps between real devices rather than on them: the largest phone is about 27rem wide and the smallest tablet about 46.5rem, so 40rem leaves a tablet in portrait its docked rail, and 75rem is the width at which the content column still reaches 40rem with both rails docked. The height condition catches a phone in landscape and a very short desktop window, which is the same situation. Phones therefore never get a bounded shell, and with that the mobile address bar keeps working: it only retracts in response to document scrolling.

Anything else is a container query. Whether the shell fits is a viewport question; whether a component fits is not, because the same viewport means a different content width depending on what is docked beside it. The content columns declare `container: content / inline-size`, and the list's card stacking, the field grid's single column and the layout preview's labels query that instead.

## Page head

`cms-page-head.css` styles the head and the toolbar of every screen. The order is fixed and every part is optional:

```html
<header class="head">
	<div class="titles">
		<nav class="breadcrumb">…</nav>
		<div class="line">
			<h1>…</h1>
			<span class="cms-count">…</span> <span class="cms-status …">…</span>
		</div>
	</div>
	<div class="actions">…</div>
</header>
```

A screen with a single title can put the `h1` straight into the head; `.titles` and `.line` are only needed once something joins it. The actions stay hand-written per screen, since a save split-button, a create menu and an upload button share nothing but their side of the head.

`.toolbar` is a row of the content column, above the scroller and outside it, so a search field or a view toggle stays put while the content moves.

## Action menus

Block pickers, block/entry row actions, menu-tree actions, and richtext menus use `.cms-action-menu` with `popover="auto"` and `data-action-menu`. Their buttons use `type="button"`, `popovertarget`, and `aria-haspopup="menu"`. Keep the surface beside its trigger in the DOM, within any owning form; the top layer handles painting without reparenting.

`panel.ts` installs the shared `$lib/action-menu` behavior for PHP and Svelte markup. It supplies expanded state, naming, keyboard navigation, focus restoration, and placement within the viewport and clipping panes. `data-align` accepts `start` (default), `end`, or `center`; `--width` overrides the normal 13rem width. Items can contain an icon, text, and `.shortcut`; use `hr` for separators, native `disabled` or `aria-disabled="true"` for unavailable actions, `.danger` for destructive actions, and `.is-active` for selected actions.

Action activation closes the menu before the consumer handler runs, so a handler can focus new content or open a modal without a later menu cleanup reclaiming focus. Tree actions opened with `.` return focus to the row; ordinary triggers regain focus on Escape.

A split button pairs a default action with its alternatives: `.cms-split-button` holds the default `.cms-button`, a `.cms-button.toggle` of the same variant carrying a chevron and a translated accessible name, and the `.cms-action-menu` the toggle opens with `data-align="end"`. A choice may be a submit button with a `form` attribute: the menu closes first and the native submit still carries the choice as its submitter, so its `name` and `value` reach the server. A menu button has no action of its own — one `.cms-button` with a label, a trailing chevron and `popovertarget` — and is the right one where no choice is a natural default, since a split button promises one.

Theme rules targeting `.kebab-menu`, `.picker-menu`, or `.richtext-dropdown-menu` must target `.cms-action-menu` instead.

## Shared shells

`.cms-modal` is a native `<dialog>` shared by server-rendered settings/confirmations and bridge-mounted content. Its backdrop, border, scrolling, header, close button, and footer come from `cms-modal.css`; the content parts are `.modal-header`, `.modal-title`, `.modal-body`, and `.modal-footer`. The normal width is 48rem, `data-size="compact"` 32rem and `data-size="wide"` 72rem, each bounded by the viewport. A footer is optional: settings edit live and need no invented Apply step. Do not move a server-rendered dialog out of its form to escape clipping; the top layer handles that. The block catalog is such a body, as is the blocks layout preview.

`.cms-tabs` is a row of tabs over a line. The row is the `role="tablist"`; each `.tab` inside is a `role="tab"` button carrying `aria-selected`, `aria-controls` and a roving `tabindex`, so the row is one tab stop and the arrow keys move between tabs. The panels sit wherever the screen puts them as `role="tabpanel"` elements labelled by their tab. A server-rendered screen marks the block holding the row and its panels with `data-tabs`, and `behaviors/tabs.ts` moves the selection; the markup arrives with one tab selected and the other panels `hidden`, so nothing flashes before the script runs.

Built-in panel icons use the checked-in regular Bootstrap collection in `panel/icons/`, shared by `Cosray\Panel\Icon::render('plus')` and `<Icon name="plus" />` from `panel/src/components/Icon.svelte`; the collection README records its version, license and mappings. No panel action icon requires a network request. Icons inherit `currentColor`, `--cms-icon-size` defaults to `1em`, and they are decorative: put a translated accessible name on an icon-only button or link, not on its SVG.

## Fields

Each `.cms-fields` grid aligns the start of neighbouring controls beneath the tallest label in their row; descriptions and validation messages stay directly below their own control. `#[Width]` controls horizontal placement and `#[Rowspan]` counts complete field rows. Fieldsets and the field grids inside Entries and Blocks align independently, hidden labels contribute no height, conditional fields leave the grid while hidden, and the narrow-screen layout stacks fields without reserving label space.

Fallback content is a secondary, display-only state, not a value style, and it must not obscure the empty control's focus path: text, richtext and code layers disappear on focus, block previews are `inert` with their editing chrome hidden rather than dimmed. The hooks a theme may restyle are `.cms-fallback-source` for native fields, `.cms-richtext-fallback`, `.cms-code-editor-fallback`, `.cms-media-fallback`, and `.variant.is-fallback-preview` with `.cms-blocks-fallback-source` for an asymmetric block source. Preserve the distinction between source preview and editable target content.

## Styleguide

`/<panel-path>/styleguide` renders every component against the current stylesheets, plus a theme toggle. It is registered only when `app.debug` is on, and sits behind the same authentication as the rest of the panel.

It exists for the states that break quietly and real content rarely produces: empty, disabled, error, a title long enough to truncate, a node with four locale paths in the inspector. It is built to be checked, by a person or by a tool: `?section=<key>` narrows the page to one section and `?theme=light|dark` forces a theme, so one URL answers one question. Every section carries its key as `data-section`, and every sample a `data-sample` hook — `input:readonly`, `field:invalid` — so a check addresses a state instead of hunting for it by position.

Two rules keep it honest:

- **Render partials, never copies.** Fields come from `panel/views/field/*` with fixture data. A styleguide with its own copy of the markup drifts, and a stale styleguide is worse than none. Where a screen has no extractable partial yet, its section is inline and marked, and it is replaced when that screen is ported.
- **Read tokens from the stylesheet.** The token tables are parsed out of `tokens.css` at request time, so the palette cannot drift from what is documented.

Adding a component means adding it here too.

## Migration

The panel is mid-redesign. Older stylesheets still use unprefixed class names and a flatter structure. They are converted per screen, not in one sweep: the old file is deleted when its screen lands. Both conventions coexist inside the `panel` layer until then.

New CSS follows the convention above, without exception.
