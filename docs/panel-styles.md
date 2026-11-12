# Panel styles

This guide explains the current CSS approach and the constraints worth checking when changing it. Naming, component boundaries, layout, and visual choices are working conventions, not a frozen design system. Experiments may depart from them; assess the result in real screens and the live styleguide rather than treating consistency with this document as the acceptance test.

The stylesheets in [panel/styles/](../panel/styles/) describe the implementation. [tokens.css](../panel/styles/tokens.css) is the token list; the styleguide renders those tokens and component states directly.

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

### Stage marks

A node's stages (the main version, the editorial draft, the user's own changes) appear as icons, dots, or the compact main icon with small dots beside it, in the editor head, lists, filters, and the status tab. The `--cms-color-stage-*` tokens colour them everywhere:

- `main`, `draft`, `mine`, and `problem` colour a stage that holds something; each has an `-ink` variant for the icon drawn on its tint.
- `idle` is the icon of an empty stage, `offline` the icon of a main version that is not published. Dots show the latter as a ring in the main colour.
- `empty` fills the dot of an empty stage.

The `--cms-stage-*` tokens shape them: `mark-size` and `mark-size-sm` for icons (head and lists), `dot-size` and `pip-size` for the dots, and `tint` for the strength of an icon's background. A halo rings every stage that holds something. Icons have none by default and dots a soft 2px band; `halo` and `dot-halo` set the ring width, `halo-gap` and `dot-halo-gap` its distance from the mark, and `halo-strength` how much of the stage colour it shows. Neighbouring marks move apart by the halo's reach.

```css
@layer theme {
	:root {
		--cms-stage-halo: 1.5px;
		--cms-stage-halo-gap: 1.5px;
		--cms-stage-halo-strength: 45%;
	}
}
```

Sizes and halo widths need units, also when zero, because they are used in `calc()`. The styleguide's status section shows each look with and without a halo.

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

[scroll.js](../panel/src/behaviors/scroll.js) preserves collection-list position across a tree swap. Account for navigation, expanded trees, and focus restoration when changing scroll ownership.

## Action menus

Built-in PHP and script-built action menus use `.cms-action-menu` with `popover="auto"` and `data-action-menu`. Triggers use `type="button"`, `popovertarget`, and `aria-haspopup="menu"`. Keep a menu beside its trigger inside the owning form; top-layer painting avoids clipping without reparenting controls out of their submission context.

[The shared behavior](../panel/src/lib/action-menu.js) supplies expanded state, naming, keyboard movement, focus restoration, and placement. `data-align` accepts `start`, `end`, or `center`; `--width` changes the menu width. A trigger carrying `data-menu-inside` gets the menu inside its own box when there is room: centred, with the top edge at the trigger's middle so the first choice sits under a centred mark, or centred outright when the lower half is too short; a smaller trigger gets the usual placement below or above. Items can contain icons, text, and `.shortcut`; `hr` separates groups, native `disabled` or `aria-disabled="true"` marks unavailable actions, `.danger` destructive actions, and `.is-active` a selected choice.

Activation closes the menu before the consumer handler runs, so opening a dialog or focusing new content is not undone by later cleanup. An item may be a submit button with a `form` attribute; its submitter name/value still reaches the server. `.cms-split-button` pairs a default action with alternatives; use a single menu trigger when there is no useful default action.

These hooks describe the current shared behavior. If changing the markup, update its PHP and JavaScript consumers together and verify the [keyboard behavior](panel-keyboard.md#action-menus).

## Dialogs

Every panel dialog is a `.cms-modal`, a native dialog with `.modal-header`, `.modal-title`, `.modal-body` and an optional `.modal-footer`; `data-size` selects `compact`, `wide` or `stage`. [Modal lifecycle](controls.md#modal-controls) documents cancellation, cleanup, ownership and focus. There are three ways to one, by what it holds:

- A question before an action on a form or link: `hx-confirm` with the question, plus `data-confirm-action` for the button's label and `data-confirm-tone="danger"` when the action destroys something; `data-confirm-title` titles the dialog when the action alone does not. A global handler asks it in a panel dialog before htmx sends the request.
- A question at the button that asks it: a `popover` marked `data-confirm`, named by the button's `popovertarget`, with `role="dialog"` and a title for `aria-labelledby`. [confirm.js](../panel/src/behaviors/confirm.js) places it below or above the button, at its end with `data-align="end"`, focuses the element marked `data-confirm-focus`, and closes it on `data-confirm-cancel` or its submit button. Use it where the question shows what the action takes along, as applying and discarding in the node editor do.
- A question or notice from a script: `confirm()` and `alert()` of [modal.js](../panel/src/lib/modal.js), which resolve with the answer. `question(element, message)` reads the same `data-confirm-*` attributes, so a script-driven action is annotated like an `hx-confirm` one. `frame()` and `button()` build the parts of other script-built dialogs, which `openModal()` mounts.
- Content rendered on the server, such as settings or a gated bulk action: the [dialog component](../panel/views/component/dialog.php) with a `title`, an optional `size`, and a `name` that becomes `data-dialog`; its slot holds the body and footer. A button with `data-dialog-open` naming it opens the nearest dialog of that name, the first an ancestor holds as a direct child, so a block's settings button reaches its own dialog and not the field's around it.

A footer puts Cancel (`secondary`) first and the action last: `primary`, or `danger solid` when it destroys something. Label the action with what it does, such as Delete or Discard changes, never OK or Yes. A destructive question starts on Cancel, any other on its action; Escape, the backdrop and the close button always cancel.

## Toasts

A toast reports an event that has no place of its own on the page: a finished upload, a refused action, a background failure. State that belongs to a place stays there, such as the editor's save state in its head or validation errors at their fields. Toasts gather in the document's `#cms-toasts` live region; [toast.js](../panel/src/lib/toast.js) shows successes and notes for five seconds, paused under the pointer or focus, and keeps warnings and errors until closed.

- From a script: `toast(kind, message)` of toast.js, with `success`, `info`, `warning` or `error`. Element controls reach it through the [bridge](controls.md#the-windowcosray-bridge).
- From a response: include [component/toast](../panel/views/component/toast.php) with `kind` and `message`. It appends a plain `data-toast` element to the region out of band, which toast.js turns into a toast after the swap; on a full page load, toast.js moves it into the region first. A route whose response is only a toast renders the `toast` view.
- Across a redirect: the action adds a `notice` query param, which the page it leads to turns into toasts and [bulk.js](../panel/src/behaviors/bulk.js) strips from the address. Collections read `key:count` pairs, such as the bulk results or `deleted:1` after a delete in the editor; users and menus read one key each. A notice that offers an action, such as undoing a menu move, stays a banner on the page.

## Shared shells and fields

`.cms-tabs` and [tabs.js](../panel/src/behaviors/tabs.js) provide tab selection with labelled panels and roving focus. Built-in icons come from [panel/icons/](../panel/icons/README.md) through PHP and JavaScript adapters; put an accessible name on the action rather than its decorative SVG.

Field grids align neighboring controls while descriptions and errors remain associated with their own field. [Fallback previews](controls.md#content-language-and-fallback-previews) are display-only: visual experiments must preserve the distinction between a preview and the editable target value, including a usable focus path.

## Styleguide

`{panel.path}/styleguide` is available when `app.debug` is enabled, behind panel authentication. It is a laboratory for current components and difficult states, not a visual specification that every experiment must preserve.

`?section=<key>` isolates a section and `?theme=light|dark` selects a theme. `data-section` and `data-sample` hooks make states addressable for browser checks. Tokens are read from the stylesheet; existing component samples render shared partials where possible, avoiding copies that silently drift.

Add samples when they help expose a meaningful state or compare alternatives. Check empty, error, disabled, read-only, long-content, narrow-viewport, light/dark, and keyboard states as relevant. A successful experiment can change the shared component and its samples together; it does not need a new permanent rule to justify it.
