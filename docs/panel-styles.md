# Panel styles

This guide explains the current CSS approach and the constraints worth checking when changing it. Naming, component boundaries, layout, and visual choices are working conventions, not a frozen design system. Experiments may depart from them; assess the result in real screens and the live styleguide rather than treating consistency with this document as the acceptance test.

The stylesheets in [panel/styles/](../panel/styles/) and Svelte component styles describe the implementation. [tokens.css](../panel/styles/tokens.css) is the token list; the styleguide renders those tokens and component states directly.

## Cascade layers

[layer/document.php](../panel/views/layer/document.php) declares the order before styles load:

```css
@layer tokens, reset, panel, plugin, theme;
```

`tokens` holds design values, `reset` browser normalization, and `panel` built-in components. Plugin CSS uses `plugin`; stylesheets configured through `panel.theme` use `theme`. Layer order beats specificity for normal declarations, allowing project themes to override panel styles without a selector arms race. Unlayered rules and `!important` have different cascade behavior; consider that when debugging an override.

## Tokens and theming

The current tokens have three roles:

- Primitives: palette, spacing, radius, and type-scale values used internally.
- Semantic tokens: values such as `--cms-color-surface`, `--cms-color-text`, and `--cms-color-accent`.
- Component tokens: values such as `--cms-button-primary-bg` and `--cms-sidebar-width`.

Semantic and component tokens are the preferred project override points, but names and boundaries may still change before release. Prefer them over copying palette values into components: a literal light background can defeat dark mode even when everything around it follows the theme.

```css
/* A stylesheet listed under panel.theme. */
@layer theme {
	:root {
		--cms-color-accent: #1f3f72;
		--cms-color-text-on-accent: white;
		--cms-font-family: "Example Sans", ui-sans-serif, system-ui, sans-serif;
	}
}
```

Self-hosted fonts need their `@font-face` declarations as well. Check foreground/background pairs together in both themes. A fixed override applies to both; use `light-dark()` when they need different values.

Keep ordinary raw palette values in `tokens.css` where practical. Fixed-color contexts such as a scrim, arbitrary media, or a document preview need their own judgment. Code controls have `--cms-code-*` tokens for their intentionally dark presentation. Mix hover colors against semantic tokens rather than literal white or black so the direction works in both themes.

### Dark theme and contrast

`color-scheme: light dark` lets `light-dark()` tokens follow the operating system. `data-theme="light"` or `"dark"` on `<html>` forces a scheme in the styleguide. A component-level `color-scheme` declaration can pin inherited tokens and native controls to one theme; use it only when that local change is intended.

A project can restrict the entire panel to one scheme:

```css
@layer theme {
	:root {
		color-scheme: light;
	}
}
```

This also outranks the styleguide's theme toggle. Theme overrides of border and text tokens can replace the panel's `prefers-contrast: more` adjustments, so verify that mode separately.

In forced-color modes, shadows and gradients can disappear. Focus and state must remain recognizable without them. Existing controls retain a transparent outline that the system can paint; replacing it with `outline: none` would lose that fallback. System color keywords such as `Canvas`, `ButtonText`, and `Highlight` are appropriate for explicit forced-color adjustments.

### Shared controls and depth

`.cms-input`, `.cms-select`, and `.cms-textarea` provide the standard border, fill, focus, and state styling. `--cms-control-height` coordinates normal control density. Reuse these for ordinary controls; a different presentation, such as a bare block editor, may override them deliberately. Check read-only values remain readable/selectable, disabled states remain distinguishable, and icon-only actions have accessible names.

Depth uses the `--cms-shadow-*`, `--cms-gradient-raised`, and `--cms-pane-shadow` tokens. A flat theme can remove them. Gradients live in `background-image` so a hover can change the background color underneath; removing a control's border and fill may also require removing its shadow.

## Class names

The usual pattern is a `cms-` component root with nested part names and `is-`/`has-` state classes:

```css
.cms-list {
	& .row {
		&.is-selected {
			background: var(--cms-color-selected);
		}
	}
}
```

Prefer shallow selectors and files named after their component. Element selectors are useful where the markup is owned; classes help when a part varies or needs a behavior hook. Local custom properties suit value variations, but extra selectors or a different file boundary can be clearer for structural differences.

Nested selectors match the whole subtree, not just a component's own parts. A generic `.row` in a shell may accidentally match a child list's rows. Distinct part names, direct-child selectors, or `@scope` can prevent that. Self-nesting field grids already use scoped styles:

```css
@scope (.cms-fields) to (.cms-fields) {
	.label {
		/* Only this field grid's labels. */
	}
}
```

Markup that changes place keeps one form. A block of the blocks canvas renders the same on the field's grid and as a part of a split, because splitting and removing parts move blocks between the two; its place-dependent controls — the inserter, the resize edges, the action menu's entries, the settings dialog's layout numbers — list the places they serve in `data-places` (`block`, `columns`, `rows`), and [cms-blocks-editor.css](../panel/styles/cms-blocks-editor.css) shows them from where the block sits: `.grid > .block`, or `.parts > .block` inside a `.block.is-split` whose `data-split` names the direction. The split's own chrome carries no places.

## Layout and scrolling

The current shell uses bounded, independently scrolling regions on larger screens and document scrolling on small or short viewports. See [cms-shell.css](../panel/styles/cms-shell.css), [cms-page-head.css](../panel/styles/cms-page-head.css), and each screen stylesheet for actual breakpoints and pane geometry rather than maintaining another numeric specification here.

Constraints to consider when trying another layout:

- A flex scroller and its flex ancestors need `min-height: 0` to shrink; otherwise content can grow instead of scrolling.
- Nested scrollers interact with mobile address bars and on-screen keyboards. Test short viewports as well as narrow ones.
- A scrollable region needs a keyboard path into its content. Add a focus target when none exists, not an extra empty tab stop ahead of already focusable controls.
- Container queries fit components whose available width changes with surrounding rails; viewport queries fit shell-level decisions. These are useful defaults, not a restriction on additional breakpoints.
- Shared pane tokens coordinate edges where screens meet the frame. Changing the shell may require changing their consumers together.

[scroll.ts](../panel/src/behaviors/scroll.ts) preserves collection-list position across a tree swap. Account for navigation, expanded trees, and focus restoration when changing scroll ownership.

## Action menus

Built-in PHP and Svelte action menus use `.cms-action-menu` with `popover="auto"` and `data-action-menu`. Triggers use `type="button"`, `popovertarget`, and `aria-haspopup="menu"`. Keep a menu beside its trigger inside the owning form; top-layer painting avoids clipping without reparenting controls out of their submission context.

[The shared behavior](../panel/src/lib/action-menu.ts) supplies expanded state, naming, keyboard movement, focus restoration, and placement. `data-align` accepts `start`, `end`, or `center`; `--width` changes the menu width. A trigger carrying `data-menu-inside` gets the menu inside its own box when there is room: centred, with the top edge at the trigger's middle so the first choice sits under a centred mark, or centred outright when the lower half is too short; a smaller trigger gets the usual placement below or above. Items can contain icons, text, and `.shortcut`; `hr` separates groups, native `disabled` or `aria-disabled="true"` marks unavailable actions, `.danger` destructive actions, and `.is-active` a selected choice.

Activation closes the menu before the consumer handler runs, so opening a dialog or focusing new content is not undone by later cleanup. An item may be a submit button with a `form` attribute; its submitter name/value still reaches the server. `.cms-split-button` pairs a default action with alternatives; use a single menu trigger when there is no useful default action.

These hooks describe the current shared behavior. If changing the markup, update its PHP and Svelte consumers together and verify the [keyboard behavior](panel-keyboard.md#action-menus).

## Shared shells and fields

`.cms-modal` is a native dialog shared by server-rendered settings and bridge content. Its parts are `.modal-header`, `.modal-title`, `.modal-body`, and optional `.modal-footer`; `data-size` selects a size variant. [Modal lifecycle](controls.md#modal-controls) documents cancellation, cleanup, ownership, and focus, which matter independently of its appearance.

`.cms-tabs` and [tabs.ts](../panel/src/behaviors/tabs.ts) provide tab selection with labelled panels and roving focus. Built-in icons come from [panel/icons/](../panel/icons/README.md) through PHP and Svelte adapters; put an accessible name on the action rather than its decorative SVG.

Field grids align neighboring controls while descriptions and errors remain associated with their own field. [Fallback previews](controls.md#content-language-and-fallback-previews) are display-only: visual experiments must preserve the distinction between a preview and the editable target value, including a usable focus path.

## Styleguide

`{panel.path}/styleguide` is available when `app.debug` is enabled, behind panel authentication. It is a laboratory for current components and difficult states, not a visual specification that every experiment must preserve.

`?section=<key>` isolates a section and `?theme=light|dark` selects a theme. `data-section` and `data-sample` hooks make states addressable for browser checks. Tokens are read from the stylesheet; existing component samples render shared partials where possible, avoiding copies that silently drift.

Add samples when they help expose a meaningful state or compare alternatives. Check empty, error, disabled, read-only, long-content, narrow-viewport, light/dark, and keyboard states as relevant. A successful experiment can change the shared component and its samples together; it does not need a new permanent rule to justify it.
