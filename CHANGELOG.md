# Changelog

## [Unreleased](https://codefloe.com/cosray/cms/compare/0.2.0...HEAD)

### Breaking Changes

- Reworked the menu data model. `menus.description` is now a jsonb locale map instead of text, and `Cosray\Menus::create()`/`update()` take an `array<string, string>` rather than a string — apps writing menus through the API must pass a map (`['de' => 'Hauptmenü']`, or `['zxx' => '…']` when not language-specific). `Finder\Menu` now skips hidden items by default; pass `hidden: true` to include them. New columns: `menus.max_depth` (NULL = unlimited) caps how deep a tree may be built and is enforced on every write through the API, `menu_items.hidden` takes an item and its subtree out of the site, and both tables gained `created`/`changed`/`creator`/`editor` (writes take an optional `Cosray\Actor`, defaulting to the system user). A composite foreign key now keeps a parent inside its own menu at the database level — a database with an existing cross-menu parent fails migration `000000-000029` and must be repaired first. Menu items linking a node are finally indexed in `node_references`, so deleting such a node is no longer silent; **run `php run db:references` once after upgrading** to pick up the existing edges.
- Added the `manage-menus` permission, held by `superuser` alone, and put creating, deleting, and renaming a menu behind it. `edit-menus` (superuser and admin) still opens the area and covers everything inside a menu — its items and its description. A menu's handle is what templates fetch it by, so the set of menus is part of the site's markup contract rather than its content. Admins now get `403` on `.../menus/create` and `.../menus/{menu}/delete`, see the handle field disabled, and have a posted handle ignored by `POST .../menus/{menu}/edit`. Sites that relied on admins managing menus need a superuser for those actions; `Permissions::add()` cannot currently grant it (it writes to an instance the user lookup never reads).
- Plugin ids are validated at boot: lowercase letters, digits and dashes, with at least one dash required (`acme-shop`, not `shop`) — the custom-element naming rule, applied so dashless names stay reserved for cosray's own configuration keys and a plugin's `{id}.{option}` config namespace can never collide with a future built-in setting. No published plugin is affected; a plugin with a dashless id must rename (which also renames its asset URLs, template namespace, and route names).
- Replaced the entries editor island with server-rendered entry rows. Entries now render as a typed repeater: rows are regular field wrappers at deep form names, one inert template per allowed entry type stamps fresh rows client-side, and saving patches rows by uid. The `cosray-entries` element and its `cosray:entries` module are gone, `Entries::properties()` no longer exposes `entryTypes` or `init` (the per-type field tables live in the control descriptor's props), and entries markup is styled through the new `.cms-entries`/`.cms-entry` classes in `@layer panel` — panel themes that targeted the island's internals must adjust. An entry type containing another `Entries` field is now rejected at boot. Stored content is unchanged; see [docs/controls.md](docs/controls.md) for the row form-name scheme and the save semantics.
- Prefixed all panel design tokens with `--cms-` and made semantic and component tokens the public theming contract. Existing `panel.theme` stylesheets must rename overrides such as `--color-accent` to `--cms-color-accent` and `--sidebar-width` to `--cms-sidebar-width`; primitive palette, spacing, radius, and font tokens are now internal and should not be overridden.
- Moved the installed panel client out of the public directory. The new `path.panelAssets` setting holds the directory and defaults to `{path.root}/panel/static`; `panel:install` writes there, and the panel serves the files through its existing `{path.panel}/static/...` route. This frees the public directory of a `{path.panel}` directory, which web servers using a `try_files $uri $uri/ ...` fallback served — or answered `403` for — instead of routing to the panel. Downstream apps must re-run `php run panel:install`, delete the stale `public{path.panel}/` directory, and ignore the new target in VCS. `panel:install` also replaces its `--panel` and `--public` options with a single `--target`.
- Made node persistence request-neutral. `Cosray\Node\Store::save()` and `create()` now take `Locales` plus an explicit `Cosray\Node\Actor`; `delete()` takes an `Actor`. Request/session lookup moved to the panel controller. `Field\Owner::request()` became `origin()`, and `Node\Factory::proxy()` now takes the CMS `Context` instead of a request. Custom low-level store, owner, or proxy integrations must update.
- Renamed the `edit-pages` role permission to `edit-nodes`. Permissions are derived from the role in code, not stored, so no migration is needed — but an app calling `$user->hasPermission('edit-pages')` must be updated.
- Renamed the linked menu item type from `page` to `node`, and the menu item's node reference key from `page` to `node`. Migration `000000-000024` rewrites both inside `menu_items.data`. `$menu->html()` output is unchanged; application code branching on `$item->type()` must compare against `'node'`.
- Replaced `Cosray\Node\ViewRenderer` with `Cosray\Node\View`, a node-bound view service in the autowired set. `$this->view->render([...])` renders the node's own template and returns a `Response`; `output()` returns the rendered template as a string. This drops the hand-rolled `ViewRenderer::renderPage($this, Factory::fieldNamesFor($this), ...)` helpers from application nodes, and the `renderPage()`/`renderNode()` pair, which differed in both return type and template variable, is gone. `Cosray\Controller\Node` no longer takes a `Container`.
- Templates receive the node as `$node`, for a served page as well as for a node embedded through `$cms->render()`. The page-only `$page` binding is gone and must be renamed in every template. The wrapper is now built through the node factory too, so `$node->children()` works in any template; it previously threw unless the wrapper came from a finder.
- Renamed `Cosray\Contract\ProvidesRenderContext` to `Cosray\Contract\ViewContext` and `renderContext(): array` to `viewContext(Wrapper $node): array`. The hook receives the same wrapper the template gets — `$node->meta`, `$node->path()`, `$node->children()` and field values are all available — so template preparation can move onto the node. It now also runs for embedded renders (`$cms->render()`), not just for pages.
- Fixed `$cms->render()` by uid: the handle lookup used `one()`, which throws on an empty result, so the uid fallback was unreachable and a uid raised `UnexpectedResultCount` instead of rendering. An unknown id now raises the intended "Renderable node not found".
- Renamed the frontend controller from `Cosray\Controller\Page` to `Cosray\Controller\Node`; it serves nodes at their public path, and nothing in the model is a page. Route names are unchanged.
- Renamed the template-facing node wrapper from `Cosray\Node\Node` to `Cosray\Node\Wrapper`. Code calling `Node::unwrap()` or type-hinting the wrapper must use `Wrapper`; templates are unaffected because they receive the object, not the class name.
- Replaced `Cosray\Contract\HandlesFormPost` with one interface per HTTP method: `Cosray\Contract\HttpGet`, `HttpPost`, `HttpPut`, and `HttpDelete`. The methods (`httpGet()`, `httpPost()`, …) take no arguments — read the body from the autowired `Celema\Core\Request`, or through the new `Cosray\Util\Form::body()` for PUT and JSON payloads, which PHP does not parse into the request body. `formPost(?array $body)` implementations become `httpPost()` with `$body = Form::body($this->request)`.
- Frontend routing accepts PUT and DELETE on page paths, and node hooks now run before Cosray's own handling: a POST to a page with `Accept: application/json` reaches `httpPost()` instead of answering `400`, and a node implementing `HttpGet` also answers JSON-negotiated GETs. Methods without a hook are unchanged.
- Dropped the two duck-typed dispatch paths on the frontend page controller: a non-public `formPost()` is no longer invoked reflectively, and a public `render()` method no longer overrides the node view. Implement `HttpPost` and `HttpGet` instead.
- `/preview/...slug` dispatches like the public path, so `HttpGet` drives the panel preview too, and an unresolvable preview path answers `404` instead of raising a `TypeError`.
- Moved the icon provider interface from `Cosray\Contract\Icons` to `Cosray\Icons\Provider`. `Cosray\Contract` now holds behavioral interfaces only; the container tag and binding use `Cosray\Icons\Provider::class`.
- Collapsed the behavioral interfaces into a single `Cosray\Contract` namespace and renamed `HasInit` to `Init`. `Cosray\Node\Contract` is gone: `HandlesFormPost` and `ProvidesRenderContext` moved up, and the `Title`/`HasInit` compatibility interfaces were removed. Update imports to `Cosray\Contract\{Embedded, Init, Title, HandlesFormPost, ProvidesRenderContext}`. `Title` and `Init` were never node-only — both also apply to embedded classes, which is why the node-scoped namespace went away.
- Adopted the attribute-based command API of `celema/console` 0.4: all commands in `Cosray\Commands` are now plain `#[Command]` classes invoked via `__invoke(Args $args, Io $io)`. Commands that extended the removed `Celema\Quma\Commands\Command` base take a `Connection` directly and no longer build a quma `Environment`.
- Replaced framework dependencies under `celemas/*` with their `celema/*` successors and moved all integration types from `Celemas\*` to `Celema\*`. Custom application code importing those framework types must update its dependencies and imports.
- Renamed the panel translation runtime dependency from `@celemas/verba` to `@celema/verba`.
- Introduced the global asset catalog (phase 1a of the media redesign). Uploads go to the pool endpoint `POST /media/{mediatype}` (replacing the node-scoped upload route), create an `assets` table row, and store the file under the sharded key `{uid[:2]}/{uid}.{ext}` below `{path.public}{path.assets}`; identical content is deduplicated by hash. `GET /media/library` lists the catalog for the panel's reuse picker.
- Changed stored media items from `{file}` to `{uid, meta?}` and media URLs to the uid form `/media/{type}/{assetUid}/{filename}`. Migration `000000-000019` populates the catalog, rewrites nodes, drafts, both history tables, and menu items, moves all files into the pool layout, and dumps an `asset-legacy-map.json` mapping (`legacy path → uid`) into the project root. Old owner-scoped URLs (`/media/{type}/node|menu/{owner}/{file}` and static `/assets/node|menu/...`) return `404` afterwards; richtext HTML that references them must be fixed manually using the mapping dump.
- Changed file and video field URLs from web-server-static `/assets/...` paths to PHP-served `/media/...` URLs. Configure `media.fileserver` (X-Sendfile/X-Accel) when large downloads should bypass PHP.
- Changed the `window.Cosray` bridge upload to `upload(type, file)`; it returns the catalog asset (`uid`, `url`, `filename`, ...). Element controls now receive a resolved `assets` uid map alongside `value`, and per-use media meta is only persisted when it is non-empty — empty meta falls back to the asset's catalog defaults.
- Added a top-level `assets` map to serialized node JSON (frontend content negotiation); headless consumers must resolve media item uids through it instead of reading filenames from the items.
- Removed `RenderContext::assetsPath()`; block types resolve media through `RenderContext::asset($uid)` and the catalog keys.
- Removed the legacy SvelteKit panel (`ui/`), its `/panel` routes, and the old `install-panel` command. Downstream apps must switch to the SSR/HTMX panel (`path.panel`, default `/cp`), register `Cosray\Commands\InstallPanel` in their app command runner, replace old panel install scripts with `php run panel:install`, and delete the legacy installed `public/panel/` directory when it is no longer the configured panel path.
- Removed the JSON API (`/panel/api` and the optional `path.api` mount) including the auth, user, node, and collection endpoints. Frontend content negotiation (page URLs answered with JSON for `Accept: application/json`) is unaffected; custom API routes can be registered via `Bootstrap::addRoutes()`.
- Renamed the Matrix field concept to Entries/Entry. Use `Cosray\Field\Entries`, `Cosray\Value\Entries`, and `Cosray\Value\Entry`; stored field content now uses `"type": "entries"`.
- Changed Entries fields to use node-style entry schema classes through `#[Allows(...)]` instead of field inheritance. Stored entry items now use an FQCN `type` plus nested `value`, and the panel exposes `entryTypes` metadata. Existing app data needs an app-specific migration to add the entry FQCN for each Entries field.
- `path.panel` now configures the SSR/HTMX panel path and defaults to `/cp`.
- Changed installed panel client asset URLs from `{path.panel}/build/*` to `{path.panel}/static/*`.
- Changed the panel Vite dev server switch from `app.env === 'development'` to the explicit `COSRAY_PANEL_DEV=1` environment variable.

### Changed

- Reworked how the menu tree is operated. Order and level are now separate, discrete commands instead of one drag gesture: the kebab gained indent and outdent beside its move up/down, plus insert-above and insert-below, which put a new item where you are rather than at the end of the list, and every move offers to be undone. Adding a root item moved out of the side pane into the tree, so selecting an item no longer takes the action away. The tree is an ARIA `tree` with a roving tabindex — one tab stop, arrows or `j`/`k` to walk it, `←`/`→` (or `h`/`l`) to fold and unfold, `Tab`/`Shift+Tab` (or `Alt+l`/`Alt+h`) to indent and outdent, `Alt` with up/down or `Alt+j`/`Alt+k` to reorder among siblings, `Enter` or `o` to open an item, `a`/`O` to insert a sibling below or above and `A` to add a child, `.` for its kebab, `Escape` to leave. The rules the bindings follow are written down in [docs/panel-keyboard.md](docs/panel-keyboard.md). Changing an item's level no longer requires a pointing device, which closes a WCAG 2.2 SC 2.5.7 (AA) failure. Drag and drop is unchanged. Collapse state is remembered per menu in `localStorage`, so a move no longer re-expands the whole tree. Panel themes reaching into the tree markup should note that rows carry `role="treeitem"` with `aria-level`/`aria-expanded`, child lists `role="group"`, and the card's link, collapse toggle and kebab summary now sit at `tabindex="-1"`.
- Widened the panel rail from 15.5rem to 18rem (`--cms-sidebar-width`) and let a rail entry's label wrap instead of ending in an ellipsis, so a long collection or menu name reads in full. Themes that pin the token keep their own width.
- Moved the menus into the panel rail, the way collections sit there. `<panelPath>/menus` no longer renders a listing screen: it redirects to the first menu, and only a project without menus stops at an empty state. The rail lists every menu by its description with its item count — ordered by that description, with the handle on hover — marks the open one while its tree or its edit form is on screen, and holds the create action; creating or renaming a menu now lands in that menu instead of the listing. The menu's own handle and description moved onto its tree screen as an always-visible bar under the title, together with its delete, so `GET .../menus/{menu}/edit` is gone — `POST` to it still saves, and a rejected handle comes back on the tree screen instead of on a separate form.
- `Finder\Nodes::order('title')` orders by the materialized node title for the request locale (neutral-key fallback) — the expression behind the per-locale sort indexes — instead of by a content field named `title`. Types whose title field has another name, or a computed title, previously sorted on NULL and came back in arbitrary row order; a type whose `title` content field feeds its materialized title sorts as before.
- The panel auth middleware attaches the resolved user to the request's `user` attribute, so token-authenticated panel requests carry the user for chrome gating and the stored panel-language preference, which previously only session requests saw.
- An existing menu without items now iterates nothing and renders as an empty string; `Finder\Menu` raises "Menu not found" only for an unknown menu handle. Sites that wrapped `$cms->menu()` in try/catch to survive not-yet-filled menus can drop the guard.
- Menu item locale maps (`title`, `path`) resolve through the locale fallback chain with the language-neutral `zxx` key as the last resort, matching the finder's field compilation.
- `Node\Store::delete()` refuses a node that still has non-deleted children unless the new `withChildren` flag is passed, and then soft-deletes the whole subtree (children before parents) in one transaction. The editor's delete button inherits the guard: deleting a parent with children now shows an error in the status chip instead of silently orphaning the children — their URL paths previously stayed active while the nodes vanished from the panel listing.
- Rebuilt the media screen as a three-pane workspace: a filter rail (kind checkboxes with per-kind counts, upload-date ranges, reset), the searchable tile grid, and a persistent inspector that replaces the detail drawer — same meta form, focal point, usage list, and delete. Kind set, committed search, date range, and selected file mirror into the query string (`kind`, `q`, `range`, `file`), so a filtered view or a single file can be deep-linked and survives reload. The library pickers inside editor controls and richtext modals now render the same tile grid as the screen (one shared component instead of two grid implementations); their behavior is unchanged.
- The editor form submits as one nested JSON body instead of a urlencoded POST. The panel re-encodes the collected form data at submit time using the exact `parse_str()` bracket-name semantics (pinned by the shared `contract/form-names.json` fixture), so the server receives the identical tree on either transport and PHP's `max_input_vars` no longer bounds how much content a node can carry. Native urlencoded submits remain accepted.
- Moved htmx from tracked panel source into the signed panel asset artifact. The panel build now restores the exact version through pnpm and includes `htmx.js`; applications must re-run `php run panel:install` after updating.
- Updated the bundled htmx from the 4.0.0 betas to the stable 4.0.0 release.
- Relicensed Cosray from MPL-2.0 to MIT. The `celema/*` libraries the CMS builds on and the `cosray-app` skeleton are all MIT, so the framework stack is now uniformly permissive. Panel files were already MIT and are unaffected. Bundled third-party files keep their own licenses; see [REUSE.toml](REUSE.toml). Previously released versions remain available under MPL-2.0.

### Added

- The panel dashboard now shows default entries, drafts, and media statistic cards plus the six most recently edited nodes. Applications can append or replace the ordered card list through the `$app->dashboard` property; plugins append through `Registrar::dashboardCard()`. Autowired providers implement `Cosray\Contract\DashboardCard` and return the typed `Cosray\Panel\Dashboard\Card` DTO, or `null` to omit themselves for a request.
- A collapsible preview on the menu tree screen renders the menu exactly as `$menu->html()` emits it for the frontend — dynamic `children` items expanded — and its links leave the panel for the real pages.
- The dynamic `children` menu item type: an item that expands at read time into the linked node's published, visible children — `levels` deep (1–5), ordered by title, creation, or change date — synthesized as regular node entries with live titles and paths, so such a menu section never rots as pages come and go. The panel's item form configures it (page picker, depth, order) and the editor tree shows the item as stored, labeled with its source page. Each `children` item adds node queries per render level; keep the type count small.
- Drag-and-drop reordering in the menu item tree: every card's grip handle drags within and across sibling groups, each card offers an empty drop zone for nesting while a drag is active, and a drop posts the exact parent and index through the tree's own pipeline — the server renumbers the sibling group, and an impossible move comes back as a notice with the tree unchanged. The sortable library loads on demand only on menu screens; the per-item move buttons remain the JavaScript-free fallback.
- The menu editor: every menu in the panel's menus area opens as an item tree of nested cards with tree lines, client-side collapse toggles, and a per-item action menu (add child, move up/down, delete with a confirm naming the subtree size). Item details are edited in a side pane whose state is URL-driven — `?item=` selects, `?add=`/`?add={uid}` creates — so every screen state is deep-linkable and a plain `main` swap. The form covers all item types (`node` with a page search picker, `url` with per-locale paths, `asset` with a file search picker, plain labels, and unknown custom types pass through untouched), per-locale titles behind the panel's locale tabs, "open in a new tab", a CSS class, and an icon. Validation is server-side: node/asset targets must exist, URLs must start with `/`, `http(s)://`, `mailto:` or `tel:`. The move endpoint also accepts an explicit `parent` + `index` pair — the contract the upcoming drag-and-drop uses.
- The panel gained a menus area: a listing of every menu with its item count, menu creation, editing the description, renaming the handle (items follow through the FK cascade; the form warns that templates fetch menus by handle), and deleting a menu with its items after a confirm. The area sits in the masthead next to media and is gated by the new `edit-menus` permission, granted to superusers and admins by default. `Menus::rename()` backs the handle rename.
- Node menu items without a stored `title` inherit the linked node's materialized title at read time, the same way node paths already resolve; a stored title overrides, and a deleted node falls back to the stored snapshot.
- `Menus::place($item, $parent, $index)` moves a menu item below a parent at an exact zero-based index and renumbers the sibling group in one transaction — the write primitive for drag ordering in the panel menu editor. `move()` keeps its looser append/sort-key semantics.
- Bulk duplicate on the collection listing. The selection bar's duplicate action opens a dialog with an optional "also duplicate child entries" choice; copies are created through the regular create pipeline as unlocked drafts with fresh uids and no handle, and their URL paths are regenerated — a collision with the source's path gets a random suffix, and child copies compose their routes under the copied parent's actual path. The copy's title carries a localized marker — "(Copy)" / "(Kopie)" per content language, on the subtree root only — appended to the type's writable title field (the title descriptor's field, or the conventional `title` field behind a computed title; types without one stay unmarked). A selected node already covered by a selected ancestor's subtree copy is not copied twice.
- Bulk actions on the collection listing. A checkbox column (with a select-all header box) drives a selection bar offering publish, set-to-draft, and delete for the selected rows; every action confirms in a dialog, and each dialog offers including child entries — required for delete (parents with children are otherwise skipped), optional for publish and set-to-draft, whose subtree walk skips locked descendants but continues below them. Actions post the selection to `POST /collection/{collection}/bulk/{publish,delete}`, run in one transaction, skip what they must not touch (locked nodes, uids outside the collection), and report the outcome in a notice banner on the listing. Selection is per page; the publish buttons follow the collection's `#[Listing(published: ...)]` flag.
- The library listing (`GET /media/library`) filters by a kind set and upload date. `kind` takes a comma-separated set from the filter vocabulary image/video/audio/document — audio and documents were both buried under the catalog kind `file` — and `since` cuts on the created timestamp (invalid input is ignored). The response now carries `total` (full match count) and `counts` (per-kind totals honoring `q`/`since` but not `kind`, for filter UIs), and list items include `bytes`; the upload response gained `kind` and `bytes`. Single-kind calls from pickers are unchanged.
- The media screen uploads multiple files at once: the file dialog accepts a selection, files can be dropped onto the grid, the upload button counts progress through the queue, and per-file failures are listed without stopping the rest.
- Deleting a file from the media inspector asks for confirmation first, naming the file. Previously the delete request fired on the first click — safe only for referenced files, which the reference index refuses to delete.
- The plugin configuration convention: options live in the app settings at flat `{plugin-id}.{option}` keys — the same idiom as cosray's own config — and `Registrar::option($key, $default)` reads them namespaced, with the default inline so plugins work with zero configuration. A plugin class with required constructor parameters is now rejected at boot with guidance (read options from config, or register a pre-built instance) instead of failing with a raw `ArgumentCountError`.
- Validation errors now point at their fields. The error summary renders each issue as a jump link carrying the failing value's data path; the panel resolves the path to the form control and marks it — invalid styling on the field wrapper, `aria-invalid` plus an inline message on native inputs, an error badge on the locale tab of a hidden variant and on the meta button for meta issues. Clicking a summary item reveals the target (switches the locale tab, expands collapsed entries rows, opens the meta dialog) and focuses it; editing a field clears its marks. Themes can hook `.cms-field[data-invalid='true']`, `.cms-field-error`, and `.has-error`; see the "Validation errors" section in [docs/controls.md](docs/controls.md).
- Entry sub-fields now get the editor's full field chrome by construction: per-field meta dialogs, locale tabs, descriptions, and fieldset grouping work inside entries exactly as at the top level. The editor page also no longer loads a JS bundle to render entries.
- Added `references` to the finder query DSL, so nodes can be filtered by what they point at. `references = '<uid>'` asks whether the node references that target anywhere in its content and reads the `node_references` index, which also covers richtext links; `references.<field> = '<uid>'` narrows the question to one `Reference` field and compiles to a jsonb containment test that stays on the content GIN index. Both accept `!=`, `@`, and `!@`. This closes a real gap: a `Reference` stores an ordered `{uid}` list under `value.zxx`, and no field expression could reach into it — `alertRule = 'uid'` compared the whole list against a string and always missed. Note that `references` joins `path` as a reserved word in the DSL; a content field of that name is no longer addressable in a filter.
- Added the migration-only `Cosray\Migration\LegacyRichtextHtmlConverter` for downstream imports of pre-structured-richtext HTML. Its self-contained Node artifact ships with the Composer package, so callers no longer depend on ignored panel build output, installed public panel assets, or `panel/node_modules`.
- Added lazy DI for application console command class-strings. `Cosray\Console\Commands::add(MyCommand::class)` boots the app and resolves the command from one scoped container with `Cms`, a request-free `Context`, the default content locale, and an active Verba translator. Explicit keyed factories remain supported for scalar arguments. Cosray's own `db:titles` command now uses the same runtime and no longer constructs a synthetic server request.
- Added `Cosray\Node\Writer`, `Draft`, and `Cosray\Actor` for creating CMS nodes from commands without coordinating `Node\Factory`, `Serializer`, and `Store` manually. Writer drafts expose uid, publication, visibility, parent, per-locale explicit URL paths, and field-metadata settings; writes default to the seeded system actor and can receive another explicit actor. An explicit path — for example a legacy URL preserved by an importer — skips route generation; a path that is already in use, or one keyed by an unconfigured locale, rejects the draft with a clear error instead of receiving the silent uniqueness suffix.
- Registered Core's FrankenPHP development server alongside the PHP built-in server through `Cosray\Console\Commands::server()`. Configured applications can use `php run frankenphp`, including the existing port, route-prefix, request-log filtering, and BrowserSync watch behavior.
- Added the `Cosray\Console\Commands` facade bundling the base CLI command set of a Cosray app (quma migrations, `db:*`, `panel:install`, `add-superuser`, including the previously app-wired `db:titles`) as lazy factories over the booted `App`. `server()` and `i18n()` register the dev server and translation commands per app; `runner()` returns a ready `Celema\Console\Runner` with debug taken from the app config. Application `run` scripts shrink to a thin wrapper around an `app/console.php` that returns `$commands->runner()`.
- Added signed panel asset releases (`cosray-panel-{version}.tar.gz` / `cosray-panel-nightly.tar.gz`) and the `Cosray\Commands\InstallPanel` command. The installer writes client assets to `{path.public}{path.panel}/static`.
- Declared the `--help`/`-h` option on `panel:install`, required by console's new option validation for commands that intercept the flag themselves.
- Added locale fallback for UI strings: the per-request translator follows each locale's `fallback:` chain (as configured in `Locales::add()`) before falling back to the message id.
- Added `Wrapper::label()`, which reads the materialized `nodes.title` column instead of resolving the title again per node, and switched collection list columns and the reference picker to it. The finder now selects that column, previously written but never read back. Nodes implementing `Contract\Title` no longer run `title()` once per listed row — for a dynamic title that queries other nodes, a list of _n_ rows cost _n_ resolutions. `label()` falls back to live resolution when the stored map has nothing for the active locale chain, so nodes saved before the column existed, or locales added since the last `db:titles` run, still render. Note the tradeoff: a dynamic title composed from _other_ nodes can now be stale in listings, because the map is rewritten when the node itself is saved, not when its sources change — run `db:titles` after editing a node that other nodes' titles are built from. `title()` is unchanged and still resolves live, so rendering is unaffected.
- Added `Cosray\Assets\Ingest`, the asset pipeline behind `POST /media/{mediatype}` as a service taking raw bytes plus a filename, so importers can catalog files without an HTTP request or session. Validation, SVG sanitising, hash dedup, the storage write, and the insert rollback are shared with the upload endpoint, whose JSON responses are unchanged. Rejections throw `Cosray\Exception\IngestError` carrying the translation key and parameters for HTTP callers and a plain English message for the CLI. The service takes a `Cosray\Actor` (default: the seeded system user), accepts initial asset meta (for example imported alt texts), and reports whether the asset was created or deduplicated. `Media::safeFilename()` and `Media::sanitizeSvgMarkup()` moved to `Ingest`.
- Added `Cosray\Menus`, the write API for menus and menu items; `Finder\Menu` stays the read side. Menus are created, updated, and deleted with their item trees; items are added, updated, moved, and removed with generated uid ids (dots are rejected — the read query builds a dotted path from ids), same-menu parent enforcement, per-sibling-group positions, cycle-rejecting moves, and subtree deletes. Every item write syncs the item's `image` icon and `asset` link target into the `asset_references` index, so a file linked from a menu is delete-protected like one used in content; the rebuild source covers both keys per item (`menuAssets.sql`, formerly `menuImages.sql`).
- Added the `url` and `asset` menu item types. `url` items carry a literal per-locale href under the same `path` key node items use, plus an optional `target` — `_blank` renders with `rel="noopener"`. `asset` items store the asset uid and link the file's current path at render time, replacing the practice of freezing `/assets/...` URLs into fake node items, which broke on re-upload. Unknown types keep rendering as unlinked labels.
- Node menu items that store the linked node's uid under `data.node` now resolve the node's current localized path at read time, so renaming or moving a page no longer breaks menus. The per-locale `path` snapshot in the item data remains the fallback for legacy rows (whose numeric stubs never resolved) and for vanished nodes.
- Added a per-user panel UI language, independent of the content locales. The panel negotiates it per request — user preference (new `users.panel_locale` column, migration `000000-000023`), then config `panel.locale`, then the browser's `Accept-Language`, then English — and offers a language switcher in the panel sidebar (`POST {path.panel}/locale`). Selectable languages are the locales whose `cosray` and `panel` domains both ship a catalog file.

### Fixed

- The `hidden` attribute now actually hides a field wrapper: `.cms-field`'s `display: flex` outranked the user-agent rule, so conditionally hidden fields — the menu form's type sections, `when`-condition fields — stayed visible. Found live in the kundmueller panel.

- Editor saves refuse truncated submissions instead of persisting them. A form-encoded POST past PHP's `max_input_vars` is silently cut short, and since entries rows are replaced wholesale on save, saving it would silently delete content. The editor form now renders a sentinel input as its last control; save and store reject any submission that arrives without it — htmx submits get the error box, plain submits fail with `400`.
- Fixed `Finder\Menu` duplicating menu items whose id contains a dot: the tree builder split the recursive query's synthetic dotted path, so an id like `about.team` under parent `about` was inserted twice. The tree is now built from the parent column directly.
- Fixed `$menu->html($class)` clobbering the caller's wrapper class with the last item's per-item class, and reading the loop variable after the iteration for the closing list markup.
- Menu HTML now escapes titles, hrefs, and item classes. With literal-href `url` items, unescaped interpolation would have been an injection surface; previously only trusted panel-authored node items were rendered.
- Fixed generated route paths dropping non-ASCII letters that ICU can transliterate: a `{title}` of "Gebühren" produced `/gebhren`. Values are now folded to ASCII through ICU, using the transform of the language the value is written in — CLDR's `de-ASCII` gives German its digraphs (`/gebuehren`), while a language without its own rules uses the plain `Any-Latin; Latin-ASCII` fold (Swedish "Malmö" stays `/malmo`). Characters without an ICU ASCII mapping are removed during the fold. Standalone symbols such as `®`, `©`, and `™` are dropped before folding so ICU spellings such as `(R)` and `(C)` cannot enter the path. The fold runs before the case transformers, so `uppercase`, `titlecase` and the byte-based truncation operate on ASCII. Only newly generated paths change; a node's stored path is kept on save and is never regenerated.
- Fixed the frontend catchall crashing when an unrouted request resolved to a directory under `path.public`, including `/` before a Home node exists. These requests now return `404`.
- Fixed browser-rendered panel controls showing untranslated message ids. The nested Verba catalog was still wrapped by the template renderer when JSON-encoded, producing an empty object instead of the panel messages.
- Fixed reference and rich-text link searches crashing when the database still contains nodes whose types are no longer registered. Picker queries now omit those unhydratable rows.
- Fixed interrupted end-to-end test runs leaving database rows that break later runs. The application and test harness now share a transaction that PostgreSQL rolls back on teardown or disconnect.
- Fixed image rendition generation on PHP 8.5. `gumlet/php-image-resize` 2.0.x calls the deprecated `finfo_close()`, which Core's error handler turns into an `ErrorException`, so every rendition request aborted before writing the cache file — leaving empty cache directories and broken images in the panel and on the frontend. The dependency now requires `^3.0`. Applications must run `composer update gumlet/php-image-resize`.
- Fixed crop renditions ignoring their configured `pos`: the crop position was passed into the `allow_enlarge` parameter, so every crop centered and silently enlarged. Existing crop renditions are only regenerated after the cache files are removed.
- Fixed every element control failing to load on a first editor page load when `path.panel` is not the default. Importing the `cosray-host` module defined the custom element, which synchronously upgraded the hosts already in the document — each resolving its module URL against the runtime panel base before the embedded system payload had configured it. The panel now defines the host after reading that payload, so `Could not load the editor control module "cosray:…"` no longer fires and the `window.Cosray` bridge is installed before any control mounts.
- Fixed the `add-superuser` command: it referenced a `users/addSuperuser` query script that did not exist, so every run failed after prompting. The new query inserts an active `superuser` role user (owned by the seeded system user) with a generated uid, and the command prompts through the console `Io` — including hidden password input with a repeat check — instead of `readline()`, returns exit code 1 on failure, and is covered by integration tests.

## [0.2.0](https://codefloe.com/cosray/cms/src/tag/0.2.0) (2026-06-02)

### Breaking Changes

- Rename the Composer package to `cosray/cms` and the root namespace to `Cosray`.

This release removes the `Node` / `Page` / `Block` / `Document` inheritance hierarchy and dedicated node kind modeling. Content types are now plain PHP classes with metadata attributes, and behavior is derived from route/render conventions.

- **Removed** abstract base classes `Node`, `Page`, `Block`, `Document`.
- **Removed** the `RendersTemplate` trait.
- **Removed** the dead `Fulltext` class.
- **Removed** `#[Page]`, `#[Block]`, `#[Document]` metadata attributes.
- **Changed** routability/rendering semantics to use `#[Route]` and `#[Render]` conventions (renderer fallback remains node handle).
- **Changed** finder facade class from `Cosray\Finder\Finder` to `Cosray\Cms`.
- **Changed** plugin class from `Cosray\Cms` to `Cosray\Plugin`.
- **Changed** CMS configuration ownership. Regular apps can use the new `Cosray\App` facade; advanced manual bootstraps pass `Cosray\Config` to `new Plugin($config)` instead of passing it to `Celemas\Core\App`. `Cosray\Config` no longer implements the removed core config interfaces.
- **Changed** `Cosray\Config` construction to `new Config(string $root, array $settings = [])`. App name, debug mode, environment, app secret, public path, frontend sessions, and database DSN now live in `app.name`, `app.debug`, `app.env`, `app.secret`, `path.public`, `session.enabled`, and `db.dsn` settings instead of constructor arguments or public properties. `path.public` defaults to `$root . '/public'`. `app.name` reads `APP_NAME`, falling back to `celemas`. `session.enabled` reads `SITE_SESSION_ENABLED`. `app.secret` reads `APP_SECRET`. `db.dsn` reads `DATABASE_URL`. `app.name` is not validated or normalized.
- **Changed** `Cosray\View\Boiler\Error\Handler` to read debug/env/error settings from `Cosray\Config`; its constructor now accepts config, factory, and logger.
- **Changed** error integration to use `Celemas\Core\Error` instead of the separate `celemas/error` package; custom error renderers must implement `Celemas\Core\Error\Renderer` and receive a non-null server request.
- **Changed** frontend session middleware configuration from `sessionEnabled` constructor arguments on `Cosray\App` and `Cosray\Plugin` to the `session.enabled` setting.
- **Changed** CMS session options to read `cookie_secure` from `SESSION_COOKIE_SECURE`, `cookie_lifetime` from `SESSION_COOKIE_LIFETIME`, and `gc_maxlifetime` from `SESSION_IDLE_TIMEOUT`.
- **Changed** `Cosray\App::create()` to accept a root path plus an optional settings array, create `Cosray\Config` internally, and expose the config as public `$app->config`.
- **Changed** template embedding API from `find->block(...)` to `cms->render(...)`.
- **Changed** all Field and Value classes to depend on the `FieldOwner` interface instead of the `Node` class.
- **Changed** node type-hints throughout the framework from `Node` to `object`.
- **Changed** the `Node::class` registry tag to `Plugin::NODE_TAG` constant.

### Added

- `#[Name]`, `#[Handle]`, `#[Route]`, `#[Render]`, `#[Title]`, `#[FieldOrder]`, `#[Deletable]`, `#[Permission]` attributes for node metadata.
- `Title`, `HasInit`, `HandlesFormPost`, `ProvidesRenderContext` interfaces for behavioral hooks.
- `FieldOwner` interface decoupling fields from the node hierarchy.
- `FieldHydrator` service for external field initialization (two-phase init).
- `NodeFactory` service for creating node instances via `celemas/wire` autowiring.
- `NodeSerializer` service for node data serialization, blueprint generation, and title resolution.
- `NodeManager` service for node CRUD operations (save, create, delete).
- `PathManager` service for URL path persistence.
- `ViewRenderer` service for rendering nodes to templates.
- `NodeProxy` for template-friendly access to node fields and methods.
- `NodeMeta` caching facade and `Meta` reflection reader for node metadata.
- `NodeFieldOwner` adapter bridging `FieldOwner` with `Context` and uid.
- `Plugin::NODE_TAG` constant replacing the old `Node::class` registry tag.
- Bundled Boiler renderer and error integration under the `Cosray\View\Boiler` namespace. `cosray/cms` now requires `celemas/boiler` directly, so applications no longer need the separate `celemas/cms-boiler` package.
- Default Boiler `view` renderer registration using the new `path.views` config key, which defaults to `/views` relative to `path.root`.
- `Cosray\App` facade for regular CMS applications. It wraps the core app and CMS plugin, forwards the common app and CMS configuration APIs, installs the default error handler, and adds the CMS catchall route during `run()`.
- Built-in fallback templates for Boiler error pages plus `error.*` config keys for enabling/disabling the default handler, replacing the error renderer, configuring error views, and toggling Whoops debug pages.
- Root-based `Config` initialization that loads `.env` with `vlucas/phpdotenv`, sets default `app.name` from `APP_NAME` with a `celemas` fallback, and exposes `Config::requireEnv(...)` for required environment variables.

### Migration guide

Replace inheritance with attributes and implement interfaces as needed:

```php
// Before
class Article extends Page
{
    public Text $title;

    public function title(): string
    {
        return $this->title?->value()->unwrap() ?? '';
    }
}

// After
#[Name('Article'), Route('/{title}')]
class Article implements Title
{
    #[Label('Title'), Translate]
    public Text $title;

    public function title(): string
    {
        return $this->title?->value()->unwrap() ?? '';
    }
}
```

Use the CMS app facade for regular application bootstrapping:

```php
use Cosray\App;

$root = dirname(__DIR__);
$app = App::create($root, [
    'app.name' => 'cms',
    'path.public' => $root . '/public',
]);
$app->section('Content')->collection(\App\Cms\Collection\Pages::class);
$app->node(\App\Cms\Node\HomePage::class);
$app->run();
```

When bootstrapping manually with `celemas/core`, pass the CMS config to the CMS plugin instead of the core app.

Constructor dependencies are autowired from the Registry via `celemas/wire`:

```php
#[Name('Department'), Route('/{title}')]
final class Department implements Title
{
    public function __construct(
        protected readonly Request $request,
        protected readonly Cms $cms,
    ) {}

    #[Label('Title'), Required, Translate]
    public Text $title;

    public function title(): string
    {
        return $this->title?->value()->unwrap() ?? '';
    }
}
```

## [0.1.1](https://codefloe.com/cosray/cms/src/tag/0.1.1) (2026-02-01)

Codename: Benjamin

- Added support for installing the panel from tagged releases (including alpha/beta/rc), instead of only nightly builds.
- Improved the `install-panel` command output and removed the unnecessary Quma command dependency.
- Updated the panel release workflow to support prerelease tag patterns and manual (retroactive) runs.

## [0.1.0](https://codefloe.com/cosray/cms/src/tag/0.1.0) (2026-02-01)

Initial release - Codename: Sabine
