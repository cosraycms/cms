# Panel keyboard vocabulary

The panel should be operable from the keyboard throughout, and comfortable for someone who lives in vim. That is a consistency problem, not an engineering one: the same key has to mean the same thing on every screen, or the second screen teaches users to distrust the first.

This file is that vocabulary. It is prose, not a framework — there is no keymap registry, no configuration, and each screen implements the rules locally. The consistency comes from the rules being written down, not from shared code.

## Rules

**One navigable structure is one tab stop.** A tree, a list, a grid: `Tab` enters it, and a roving `tabindex` moves inside it. The structure's own controls (links, toggles, action menus) leave the tab order at `tabindex="-1"` and are reached by key instead. Without this, `Tab` cannot be used for anything else, and a 40-row list becomes 120 tab stops.

**Arrows move the focus. `j` `k` `h` `l` shadow them.** Never the reverse, never a third meaning. Vertical is `↑`/`↓` and `k`/`j`; horizontal is `←`/`→` and `h`/`l`, where horizontal means folding and unfolding a branch or stepping in and out of it.

**The unmodified letters are a layer, and it is off by default.** Type-ahead — a letter jumping to the next entry that starts with it, which the ARIA patterns expect — needs every printable key, so a letter cannot belong to both it and a command. Everything spelled with a bare letter therefore sits behind one setting, together with `Alt` as a modifier. What is left without it is exactly the ARIA pattern's own keys, which is what someone who has not read this file expects.

**A modifier plus a direction moves the thing, not the focus.** Vertically among its siblings, horizontally through the levels. The two axes stay on separate key pairs: nothing that reorders may change nesting, and nothing that changes nesting may reorder. Conflating them into one gesture is what makes drag and drop unreliable for trees, and it does not get better on a keyboard.

Both spellings of a direction get a modifier of their own. The arrows take `Ctrl+Shift` or `Cmd+Shift`, which is what Notion, Miro and Webflow use; both are accepted rather than sniffing the platform, since neither means anything outside a text field. The vim letters take `Alt`.

**An essential browser default is never reinterpreted.** `Alt+←` and `Alt+→` are back and forward on Windows and Linux, so they carry nothing here, even though `preventDefault()` would suppress them and the worst accident — an item outdented by mistake — is visible and one click from undone. Reachability is not the test. `Cmd+T`, `Cmd+N`, `Cmd+W` are the same category, and a shortcut layer that treats them as available is a layer people learn to distrust.

**`Tab` is never rebound.** Outliners indent with `Tab`, but they can: their rows are text fields, where `Tab` has no competing meaning. Ours are focusable elements in a document, so `Tab` still means "next control" — and a user who opens a row's action menu and reaches for `Tab` to walk it would instead reshape the tree. The ARIA `tree` pattern does not ask for `Tab` either; it describes navigation and selection, and says nothing about restructuring.

**`Enter` or `e` opens the focused thing for editing. `Escape` leaves the innermost layer.** In a structure that means giving up the focus; in a dialog it means closing it. One rule, applied at whatever depth the user is.

**`o` creates below, `O` above.** Exactly the vim buffer's "open a line". Nothing creates a child directly: that is `o` followed by `Alt+→`, the way an outliner does it, so no key has to mean "but nested" and every letter keeps one meaning.

**`.` opens the focused row's action menu.** Whatever that screen calls its kebab.

**`/` focuses the screen's search.** Global, and older than the rest of this list.

**Every binding is inert inside a text field.** An `input`, `textarea`, `select`, or anything `contenteditable` keeps its own keys, including `j`, `o` and `e`.

**A key only ever drives an affordance that already exists.** Every binding here finds a server-rendered link or form and activates it, so the disabled state is the legality check and the screen keeps working without JavaScript. Build the action first, bind the key second — never the reverse.

## Modified keys match on `code`, unmodified keys on `key`

macOS composes `Option` with a letter into a different character — `Option+h` is `˙`, `Option+l` is `¬`, and which character exactly depends on the layout. The letter never arrives, so a modified binding has nothing to match but the physical key.

Unmodified letters stay on `event.key`, where the layout should decide.

The two halves disagree only on layouts that move `h` `j` `k` `l` away from their QWERTY positions — Dvorak and Colemak, not QWERTZ or AZERTY. **Accepting both `code` and `key` is not the fix**: on exactly those layouts one physical key would then match two commands, and an ordered fallback only makes the wrong answer deterministic. Live with the seam.

## What is bound today

The menu tree (`panel/src/behaviors/menu-keys.ts`) is the first screen built to these rules and is the reference implementation.

Always on:

| Key | Action |
| --- | --- |
| `↑` `↓` | previous / next visible row |
| `←` | collapse, or move to the parent when already collapsed |
| `→` | expand, or move to the first child when already expanded |
| `Home` `End` | first / last visible row |
| `Enter` | open the item in the side pane |
| `.` | open the row's action menu |
| `Ctrl/Cmd+Shift+→` `←` | indent / outdent |
| `Ctrl/Cmd+Shift+↓` `↑` | move down / up among siblings |
| `Escape` | leave the tree |

Behind the vim setting:

| Key             | Action                         |
| --------------- | ------------------------------ |
| `k` `j`         | previous / next visible row    |
| `h` `l`         | fold / unfold, as `←` and `→`  |
| `e`             | open the item, as `Enter`      |
| `o` / `O`       | insert a sibling below / above |
| `Alt+l` `Alt+h` | indent / outdent               |
| `Alt+j` `Alt+k` | move down / up among siblings  |

Adding a child has no key: `o`, then an indent. It stays in the row's action menu for the mouse.

## Turning the vim layer on

There is no interface for it, on purpose. One browser, one line:

```js
localStorage.setItem("cosray:vim-keys", "on");
```

The key is panel-wide, not per screen — the second screen with vim bindings shares this one. It lives in `localStorage`, so it is per browser rather than per user; if that ever needs to change, it moves to the user profile the way `users.panel_locale` did, which is an additive change and not a reason to build for it now.

The setting is read on every keystroke, so it takes effect without a reload.

One collision inside the layer is known and left alone: on Windows and Linux, `Alt` plus a letter reaches the browser's menu bar — `Alt+h` opens Help in Firefox. It is now borne only by people who asked for the layer, which is what made it tolerable.

## Open decisions

Both belong here rather than in whichever screen hits them first.

**Type-ahead.** The ARIA patterns for lists and grids expect a letter to jump to the next entry starting with it. Reserving `j` `k` `h` `l` for navigation gives that up everywhere. It is a real loss on a long collection list, and it has to be decided once — either the vim pair wins globally, or type-ahead does and the vim pair moves under a modifier.

**How far `Escape` unwinds.** "Leaves the innermost layer" is unambiguous while layers nest cleanly. A row inside a tree inside a dialog is not obviously three layers to a user pressing the key twice.

## Where shared code belongs

`panel/src/behaviors/menu-tree.ts` splits along a seam that will matter later: a generic half (roving tabindex, which rows are reachable, restoring focus across an htmx swap) and a menu-specific half (collapse state persisted per menu handle, the selected row as the restore target).

Extract the generic half when a **second** screen needs it, not before. Generalising from one example reliably produces the wrong abstraction, and the second consumer is what shows where the seam actually runs. The collection list is the likely candidate: it already renders `role="row"` but has no focus model, and it is where editors spend their day.

The node editor is not a candidate. It is a form, and browser `Tab` is already the right answer there.

## Discoverability instead of configuration

Bindings are not configurable, and should not become so. Most requests for configuration are really requests to find out what the keys are — a `?` overlay listing the current screen's bindings answers that at a fraction of the cost, and keeps this vocabulary honest by making its inconsistencies visible.
