# Panel keyboard vocabulary

The panel should be operable from the keyboard throughout, and comfortable for someone who lives in vim. That is a consistency problem, not an engineering one: the same key has to mean the same thing on every screen, or the second screen teaches users to distrust the first.

This file is that vocabulary. It is prose, not a framework — there is no keymap registry, no configuration, and each screen implements the rules locally. The consistency comes from the rules being written down, not from shared code.

## Rules

**One navigable structure is one tab stop.** A tree, a list, a grid: `Tab` enters it, and a roving `tabindex` moves inside it. The structure's own controls (links, toggles, action menus) leave the tab order at `tabindex="-1"` and are reached by key instead. Without this, `Tab` cannot be used for anything else, and a 40-row list becomes 120 tab stops.

**Arrows move the focus. `j` `k` `h` `l` shadow them.** Never the reverse, never a third meaning. Vertical is `↑`/`↓` and `k`/`j`; horizontal is `←`/`→` and `h`/`l`, where horizontal means folding and unfolding a branch or stepping in and out of it.

**`Alt` plus a direction moves the thing, not the focus.** The two axes stay on separate key pairs: nothing that reorders may change nesting, and nothing that changes nesting may reorder. Conflating them into one gesture is what makes drag and drop unreliable for trees, and it does not get better on a keyboard.

**`Enter` or `o` opens the focused thing. `Escape` leaves the innermost layer.** In a structure that means giving up the focus; in a dialog it means closing it. One rule, applied at whatever depth the user is.

**`a` creates a sibling, `A` a child.** The file-tree convention — `o` opens, `a` adds — from NERDTree, oil and neo-tree, which is the context a panel tree resembles, not the vim buffer where `o` opens a line.

**`.` opens the focused row's action menu.** Whatever that screen calls its kebab.

**`/` focuses the screen's search.** Global, and older than the rest of this list.

**Every binding is inert inside a text field.** An `input`, `textarea`, `select`, or anything `contenteditable` keeps its own keys, including `j`, `o` and `a`.

**A key only ever drives an affordance that already exists.** Every binding here finds a server-rendered link or form and activates it, so the disabled state is the legality check and the screen keeps working without JavaScript. Build the action first, bind the key second — never the reverse.

## Modified keys match on `code`, unmodified keys on `key`

macOS composes `Option` with a letter into a different character — `Option+h` is `˙`, `Option+l` is `¬`, and which character exactly depends on the layout. The letter never arrives, so a modified binding has nothing to match but the physical key.

Unmodified letters stay on `event.key`, where the layout should decide.

The two halves disagree only on layouts that move `h` `j` `k` `l` away from their QWERTY positions — Dvorak and Colemak, not QWERTZ or AZERTY. **Accepting both `code` and `key` is not the fix**: on exactly those layouts one physical key would then match two commands, and an ordered fallback only makes the wrong answer deterministic. Live with the seam.

## What is bound today

The menu tree (`panel/src/behaviors/menu-keys.ts`) is the first screen built to these rules and is the reference implementation.

| Key | Action |
| --- | --- |
| `↑` `↓` / `k` `j` | previous / next visible row |
| `←` / `h` | collapse, or move to the parent when already collapsed |
| `→` / `l` | expand, or move to the first child when already expanded |
| `Home` `End` | first / last visible row |
| `Enter` / `o` | open the item in the side pane |
| `a` / `A` | insert a sibling below / add a child |
| `.` | open the row's action menu |
| `Tab` / `Shift+Tab` | indent / outdent |
| `Alt+l` / `Alt+h` | indent / outdent |
| `Alt+↓` `Alt+↑` / `Alt+j` `Alt+k` | move down / up among siblings |
| `Escape` | leave the tree |

`Alt+←` and `Alt+→` are deliberately unbound: they are browser-back and browser-forward on Windows and Linux.

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
