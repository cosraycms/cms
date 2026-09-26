# Panel keyboard

This document records current keyboard behavior and the considerations behind it. Bindings and interaction models may change as the panel develops; keyboard accessibility and safe editing should remain acceptance criteria, not a reason to freeze the current design.

## Working approach

Prefer familiar browser and ARIA interaction patterns. A composite tree can use one tab stop with arrow navigation; an ordinary editor form benefits from native tab order. Choose the pattern for the actual widget rather than applying roving focus to every list or grid.

Panel shortcuts should leave text inputs, textareas, selects, and contenteditable content to their own editing keys. `Escape` should leave the innermost relevant layer without triggering a destructive action. Reordering and changing hierarchy are distinct operations; exposing both as explicit actions makes their effects easier to understand and undo.

Avoid overriding essential browser shortcuts where possible. There is a current exception: block resizing consumes `Alt+Left/Right`, which conflicts with browser history on Windows and Linux. The menu tree avoids that pair. Treat the exception as a tradeoff to revisit, not a panel-wide precedent.

## Menu tree

[menu-keys.ts](../panel/src/behaviors/menu-keys.ts) and [menu-tree.ts](../panel/src/behaviors/menu-tree.ts) implement the current tree behavior. It has one tab stop; links and actions inside a row are reached through its commands rather than adding a tab stop per control.

| Key | Action |
| --- | --- |
| Up / Down | Previous / next visible row |
| Left | Collapse, or move to the parent when collapsed |
| Right | Expand, or move to the first child when expanded |
| Home / End | First / last visible row |
| Enter | Open the item for editing |
| `.` | Open the row's action menu |
| Ctrl/Cmd+Shift+Left / Right | Outdent / indent |
| Ctrl/Cmd+Shift+Up / Down | Move among siblings |
| Escape | Leave the tree |

The optional vim layer is enabled per browser:

```js
localStorage.setItem("cosray:vim-keys", "on");
```

| Key           | Action                         |
| ------------- | ------------------------------ |
| `k` / `j`     | Previous / next row            |
| `h` / `l`     | Fold / unfold                  |
| `e`           | Open the item                  |
| `o` / `O`     | Insert a sibling below / above |
| Alt+h / Alt+l | Outdent / indent               |
| Alt+k / Alt+j | Move among siblings            |

The setting is read on each keystroke. There is no settings UI or configurable keymap currently. Browser menu shortcuts can conflict with Alt+letters; leaving the layer off avoids those bindings. Type-ahead and shortcut discoverability remain areas to explore.

### Keyboard layouts

Modified letter bindings match `event.code` because macOS Option can change `event.key` into a composed character. Unmodified letters match `event.key`. This produces a layout-dependent seam on Dvorak/Colemak; accepting both values indiscriminately can map one physical key to two commands. Check non-QWERTY layouts when revisiting the scheme.

## Action menus

The shared [action-menu behavior](../panel/src/lib/action-menu.js) works with both PHP and Svelte markup:

- Enter or Space on the trigger opens at the first enabled item; Down opens at the first and Up at the last.
- Arrows and Home/End navigate enabled, visible items and scroll them into view.
- Enter/Space activates the focused action. Tab/Shift+Tab retain browser focus movement.
- Escape closes the menu and restores the trigger, or the tree row when opened with `.`. Outside dismissal leaves focus at the clicked control.
- Menu keys do not drive the background tree. Activation closes the menu before opening a dialog or focusing new content.

See [action-menu markup](panel-styles.md#action-menus) when changing a consumer.

## Modals

Native dialogs contain focus in the active modal. Initial focus goes to a designated safe action or useful input. Escape dismisses the innermost dialog without confirming, and closing returns focus to a usable opener. An action that focuses new content closes its dialog first so restoration does not steal that focus.

Field and block settings edit live and retain values on closure; media metadata has separate Apply/Cancel semantics. Browser confirmation and unsaved-navigation prompts remain browser-owned exceptions. See [modal lifecycle](controls.md#modal-controls).

## Block controls

A multi-column block's focused grip supports resizing: Alt+Left/Right moves the end edge; Alt+Shift+Left/Right the start edge; Alt+Up/Down changes row span. The settings dialog provides number inputs for the same layout values and for the block's column and row, which move it the way a drop does. Move up/down actions move a block one row, pushing what it meets, as a keyboard alternative to dragging. Escape cancels a drag in progress.

A block's split entries open the field's picker at the action menu's trigger, focused on its first type, or split at once when the field has one type; its "Change block type" entry opens the same picker. A split's tools come before its parts, and each part's tools before its content, as a block's do. On a part's grip Alt+Left/Right trades width with its neighbour in a split into columns and Alt+Up/Down changes a part's rows in a split into rows; the start edge has no keys inside a split, and a split into rows takes no Alt+Up/Down, since its height is its parts'. The seam between two parts is a pointer handle only; the grip and the settings dialog reach the same widths.

Ghost blocks, the buttons a multi-column canvas shows in its empty cells, are tab stops after the field's rows and before its add bar. Enter or Space inserts a block into that gap; with several block types it opens the picker inside the ghost, which then behaves as the add bar's picker does.

The block catalog opens with focus in search. Down or Enter moves from search to the first match without submitting the editor. Its result buttons form one roving stop: Left/Right moves through matches, Up/Down to an adjacent row, and Home/End to the first/last match. Enter/Space inserts the chosen type. Escape closes and restores the opener; inserting closes and focuses the new row. The layout preview's presets, reload, and close are ordinary tab stops.

See [block behaviors](../panel/src/behaviors/) for current keys and focus handling, rather than applying the tree's navigation model to editable block content.

## Reference fields

The reference combobox opens eligible recent entries on focus; typing searches them. Up/Down highlights choices while focus stays in the input; Enter selects only the highlighted choice and does not submit the editor. Text-editing keys retain their usual behavior.

Tab can reach Load more or Retry before leaving the picker. Single-reference fields also expose a clear action. Escape closes the dropdown without closing a surrounding dialog; a click or arrow key reopens it, and Enter also opens a closed single-reference picker.

A single choice replaces the value, closes the dropdown, and keeps input focus. Dismissing restores the selected title without changing the value. Multi-selection remains open until its limit; reaching the limit focuses the newly selected entry's remove action. See [reference controls](controls.md#reference-fields).

## Content language and fallback previews

The content-language control uses radio-group keys when segmented and native select keys when rendered as a select. It has no panel shortcut. Validation can switch to the locale of an error. Escape in an overlay inspector closes it and restores its opener.

Fallback previews are display-only, with asymmetric block previews inert rather than extra editing stops. Focus and add actions still target the selected locale's empty value. See [fallback behavior](controls.md#content-language-and-fallback-previews).
