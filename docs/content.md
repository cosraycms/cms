# Content models

This guide describes current node and value behavior. The implementation lives in [Node/](../src/Node/), [Field/](../src/Field/), [Value/](../src/Value/), and [Finder/](../src/Finder/); these APIs are still evolving.

## Nodes and fields

Nodes are plain PHP classes with typed field properties and schema attributes. They need no base class; constructor dependencies are autowired.

```php
use Cosray\Field\Blocks;
use Cosray\Field\Text;
use Cosray\Schema\Label;
use Cosray\Schema\Required;
use Cosray\Schema\Route;
use Cosray\Schema\Translate;

#[Label('Article'), Route('/articles/{title}')]
final class Article
{
    #[Label('Title'), Required, Translate]
    public Text $title;

    #[Label('Content'), Translate]
    public Blocks $content;
}

$app->node(Article::class);
```

`#[Route]` makes a type routable. Its handle derives from the class name unless `#[Handle]` supplies it; the renderer defaults to that handle unless `#[Render]` overrides it. `#[Children(...)]` declares allowed direct child types, defaulting to none. [Schema attributes](../src/Schema/) and [node schema construction](../src/Node/Schema.php) define the supported metadata.

Inside a node class, fields expose their value through `value()`. Templates receive a `Cosray\Node\Wrapper`, whose field properties are already `Value` objects. Use `unwrap()` for raw values; string conversion uses the field's escaped or rendered output. Raw values need escaping when inserted into HTML. [Blocks](blocks.md), [richtext](richtext-format.md), and [media](media.md) have their own rendering behavior.

### Embedded fields

Reusable classes implement `Cosray\Contract\Embedded`. A typed property embeds their fields; `#[Fieldset]` optionally groups them in the panel.

```php
use Cosray\Contract\Embedded;
use Cosray\Field\Text;
use Cosray\Schema\Fieldset;
use Cosray\Schema\Label;

final class Details implements Embedded
{
    #[Label('Summary')]
    protected Text $summary;
}

final class Page
{
    #[Fieldset, Label('Details')]
    protected Details $details;
}
```

The object graph is nested, but stored keys, route placeholders, form names, and wrapper field access stay flat: `summary`, `{summary}`, and `$node->summary`, not `details.summary`. Flat names must be unique across the owner. Embedded public behavior is accessible through the embedded object.

Constructors run before fields are hydrated. `Cosray\Contract\Init::init()` runs after hydration, with embedded hooks before the outer hook. Embedded objects are transient; recursive embeds, containing-node injection, and shared embedded services are not supported. Entries and block schemas reuse embedded declarations, but do not instantiate embedded objects or run their hooks.

Title resolution prefers an outer `Cosray\Contract\Title::title()`, then an explicit `#[Title]` field or embedded provider, then one automatic embedded `Title` provider, then a flattened `Text $title`. Multiple explicit sources or automatic providers are ambiguous. See [Title\Resolver](../src/Title/Resolver.php).

### Translation and dates

`#[Translate]` uses symmetric translation. Media fields share their file list and translate metadata. `#[Translate(TranslateMode::Asymmetric)]` gives each locale its own payload. For blocks, the distinction also determines whether sub-fields translate; see [block translation](blocks.md#translation). Changing an existing blocks field's mode requires a content migration. Required asymmetric fields require the default locale, not every fallback locale.

`DateTime` stores UTC RFC 3339 with whole-second precision, such as `2026-08-05T12:00:00Z`; writes with other RFC 3339 offsets normalize to UTC. `meta.timezone` controls panel input and display, defaulting to UTC. `Date` and `Time` stay local values without offsets.

### Entries and references

An `Entries` field uses `#[Allows(Row::class, ...)]` to choose node-style row schemas. Stored rows have `uid`, FQCN `type`, and `fields` under one neutral `value.zxx` list. Entries are structured content, not page fragments: iterate their rows and render the fields needed; string conversion throws. See [entry editing and save semantics](controls.md#entries).

A `Reference` field stores an ordered neutral `{uid}` list. `#[Limit(max: 1)]` makes it single; `#[Pick(...)]` constrains eligible types, content predicates through `where`, and publication/visibility through `published` and `hidden`. Defaults include any publication state and hidden nodes, but not soft-deleted nodes. Read `uids()` or `uid()` and resolve through `$cms->node->byUid($uid)`. Eligibility is derived server-side from the field schema, not from client-supplied filters. See [Reference](../src/Field/Reference.php) and [Pick](../src/Schema/Pick.php).

## Route templates

`#[Route('/articles/{title}')]` applies to all locales; a locale map such as `#[Route(['en' => '/articles/{title}', 'de' => '/artikel/{title}'])` generates only the listed locales.

| Placeholder | Resolves to |
| --- | --- |
| `{uid}`, `{handle}`, `{fieldName}` | Current node identity or field value |
| `{parent}` | Required direct parent's active URL path |
| `{parent?}` | Direct parent's path, or an empty segment when no parent is selected |
| `{parent.fieldName}` | Direct parent's field value |
| `{parent(n)}`, `{parent(n).fieldName}` | Ancestor path or field, 1-based, up to depth 5 |

Parent path shortcuts reuse persisted paths without slugifying them. Fields resolve through the locale fallback chain and then neutral values; parent paths follow the locale fallback chain. Slash joins are normalized. `{parent?}` makes the relation optional, not the selected parent's path: a selected parent without a usable path still fails strict generation.

Field values, UID, and handle are slugified. Transformers include `lowercase`, `uppercase`, `titlecase`, `keepcase`, `dashes`, and `underscore`, with chains such as `{title|uppercase|underscore}`. Default output is lowercase with dashes. ICU folding uses the language supplying the field value, so German `Gebühren` becomes `gebuehren`. `keepcase` preserves the folded case, not arbitrary Unicode. Transformers do not apply to parent-path shortcuts; optional ancestor and optional parent-field syntax are unsupported.

[RoutePathGenerator](../src/Node/RoutePathGenerator.php) serves both strict persistence and lenient panel previews. Unresolved previews show friendly placeholders; they do not guarantee a valid save. Explicit paths take precedence over generated ones, and changing a parent's path does not cascade regeneration to its children. [RoutePathGeneratorTest](../tests/Unit/RoutePathGeneratorTest.php) covers the grammar and edge cases.

## Collections and queries

Collections are plain classes that describe panel listings. Attributes configure them; they need no base class:

```php
use Cosray\Schema\Blueprints;
use Cosray\Schema\Label;
use Cosray\Schema\Listing;
use Cosray\Schema\Types;

#[
    Label('Articles'),
    Types(Article::class, ArticleSeries::class),
    Blueprints(ArticleSeries::class),
    Listing(children: true),
]
final class Articles {}
```

`#[Types]` names the node types a collection lists, as classes or handles. The panel lists them in any state, unpublished and hidden entries included. `#[Blueprints]` names the types editors can create at the top of the listing. Hierarchy listings show roots and expand direct children, and `#[Children]` on a node supplies its child creation choices, so blueprints are often a subset of the listed types. The panel search matches the fields named by `#[Listing(search: [...])]`, UID and title by default. Further [collection schema attributes](../src/Schema/) set the label, handle, icon, badge, permission, visibility, and navigation order.

Behavior is opt-in through [contracts](../src/Contract/). `Entries` narrows the finder the panel prepares, which covers nodes in any state and is already limited to the collection's `#[Types]`. A collection implementing `Entries` may omit `#[Types]` to narrow all nodes; registering a collection that declares neither fails at boot. `Columns` replaces the default listing columns. Only collections implementing a contract are instantiated, through the container, so their constructors can take autowired services such as `Cms` or `Config`:

```php
use Cosray\Contract\Entries;
use Cosray\Finder\Nodes;
use Cosray\Schema\Types;

#[Types(Alert::class)]
final class Alerts implements Entries
{
    public function entries(Nodes $nodes): Nodes
    {
        return $nodes->filter('ignore=false');
    }
}
```

### Columns and panel ordering

The default listing shows title, last changed, and the configured status indicators. Its initial order is title ascending. Type, editor, and created are opt-in columns; type and editor have no built-in display-name sort. Finder's `type` and `editor` fields mean type handle and editor UID, not the labels displayed in those columns.

`#[Types]` and `entries()` define collection membership. The panel replaces any Finder `order()` set in `entries()` with the selected column order, before applying pagination. Declare the panel's initial order on a column, not in `entries()`.

A collection implementing `Columns` declares its own columns. `Column::defaults()` returns the default title and last-changed columns, so `[...Column::defaults(), $column]` extends them.

A column declares its own allowed sort key, order fields, and initial direction. Without `fields`, the key is also the order field. Without `direction`, the initial direction is ascending. `default: true` makes a column's sort the listing's initial order; without a flagged sort, the listing starts in the first sortable column's order:

```php
use Cosray\Column;
use Cosray\Finder\SortField;
use Cosray\Node\Wrapper;

public function columns(): array
{
    return [
        Column::new(__('Name'), static fn(Wrapper $node): string =>
            $node->lastName . ', ' . $node->firstName
        )->bold(true)->sort('name', fields: ['lastName', 'firstName']),
        Column::new(__('Last changed'), 'meta.changed')
            ->date(true)->sort('changed', direction: 'desc', default: true),
        Column::new(__('Editor'), 'meta.editor'),
    ];
}
```

Both fields in a compound sort follow the selected direction. UID ascending is appended as a unique final tie-breaker, not offered as a default user-facing sort. This makes pagination deterministic for unchanged data with equal names or dates.

Declare numeric and date semantics explicitly for custom fields. Plain field strings sort as text; formatting a column as a date does not change its database ordering:

```php
Column::new(__('Amount'), 'amount')
    ->sort('amount', fields: [SortField::number('amount')]);
Column::new(__('Publication date'), 'date')->date(true)
    ->sort('date', fields: [SortField::date('date')], direction: 'desc');
Column::new(__('Start'), 'start')->date(true)
    ->sort('start', fields: [SortField::dateTime('start')], direction: 'desc');
Column::new(__('Created'), 'meta.created')->date(true)
    ->sort('created', direction: 'desc');
```

For dated collections, flag the `date` or `start` sort as the default as appropriate. A callback-rendered column uses the same declarations: sorting still operates on stored fields, never on a fetched page or the callback's result. `SortField::number()` supports both Number and Decimal values without PHP float conversion. Dates require `YYYY-MM-DD`; datetimes require RFC 3339 values with seconds and an offset or `Z` and sort by instant. Missing and whitespace-only custom values sort last in either direction. Arrays, objects, malformed typed values, relative dates, and non-finite numbers cause query errors rather than silently receiving a misleading order.

A column without `sort(...)` is not sortable; `sort(null)` explicitly disables it. Keys must be unique, at most one sort may be the default, and a listing needs at least one sortable column. Invalid definitions fail as configuration errors. HTTP `sort` and `dir` parameters only select declared orders; unknown keys and invalid directions return 400. Omitting the direction uses the selected column's initial direction, switching headers starts in that direction, and clicking the active header reverses it. Header navigation resets pagination while preserving the listing's other state.

When updating existing collections, move `sorts()` mappings into their columns and move `defaultDir()` into each sort's `direction`; those collection methods are no longer used. Add explicit sorts to columns that previously relied on field-name inference, and remove redundant ordering from `entries()`. A title-sorting trait is no longer needed. Replace a collection's `defaultSort()` with `default: true` on the matching column's sort. Remove dead type/editor sort markers unless handle/UID ordering is explicitly intended. Old URLs requesting removed keys, including the former default `uid` option, are invalid. Code inspecting columns reads the nullable `Column::$sort` definition instead of `sortKey()`.

Collections no longer extend a `Cosray\Collection` base class. When updating existing collections, remove `extends Collection`. Move the node types of a plain `entries()` query into `#[Types]` and remove the method, including its `published(null)` and `hidden(null)` calls. Implement `Entries` for any further narrowing; it receives the prepared finder instead of reading `$this->cms`. Move `blueprints()` into `#[Blueprints]` and `searchFields()` into `#[Listing(search: [...])]`. Implement `Columns` for custom columns and replace `...parent::columns()` with `...Column::defaults()`. A collection that needs services, including the CMS, takes them in its own constructor.

### Frontend queries

Frontend queries use [Cms](../src/Cms.php) and the [Nodes](../src/Finder/Nodes.php) builder:

```php
$latest = $cms->nodes()
    ->types(Article::class)
    ->published(true)
    ->hidden(false)
    ->order('changed DESC')
    ->limit(10);

foreach ($latest as $node) {
    $title = $node->title();
    $path = $node->path();
}

$about = $cms->node->byPath('/about');
$children = $about->children();
```

Existing Finder string orders keep their direction and PostgreSQL null-placement behavior. For typed ordering outside the panel, pass structured terms, for example `->order(new \Cosray\Finder\Order(SortField::number('amount'), 'desc'), new \Cosray\Finder\Order('uid'))`. Structured terms put nulls last; direct Finder queries add their own tie-breaker when required.

`roots()` and `childrenOf($uid)` filter hierarchy. Finder's DSL supports comparisons, boolean expressions, lists, patterns, and field existence:

```text
published = true & hidden = false
parent = 'services'
type @ ['article', 'landing-page']
title.? ~* 'hello'
path.de = '/about'
references = 'target-uid'
references.related @ ['first-uid', 'second-uid']
```

`references` matches indexed node references, including richtext links; `references.fieldName` matches one Reference field. Comparing a reference field directly with a UID string does not match its stored list shape. `path` and `references` are reserved DSL names. See [QueryParser](../src/Finder/QueryParser.php), [QueryCompiler](../src/Finder/QueryCompiler.php), and their tests rather than constructing DSL expressions from untrusted input without understanding its quoting rules.

[Full-text search](fulltext.md) is explicit opt-in through `fulltext()`. Existing `search()` and `searchTitle()` use substring matching.

### Materialized titles

`title()` resolves dynamically; `label()` uses the materialized `nodes.title` locale map with a live fallback. Listings and title ordering use that map. Ordering follows the active content locale's fallback chain, then the neutral title, ignoring blank titles. It uses the locale's available PostgreSQL ICU collation, falling back to the ICU root collation or the database default. Query expressions and title indexes use the same fallback and collation policy.

A computed title depending on other nodes or external state can become stale: saving its owner refreshes it, but editing its dependencies does not. Run the app's `php run db:titles` when refreshing those titles, before `db:fulltext` when search also needs rebuilding. Sorting does not evaluate live title callbacks: a title missing from the materialized map remains absent for ordering even if `label()` can resolve it live.

`php run db:recreate-sort-index` now needs the booted application's configured locales. It creates indexes even for locales without stored titles, replaces outdated same-named definitions, and removes indexes for unconfigured locales. Run it after upgrading the title-ordering implementation or changing locale fallbacks/collation availability. Reconciliation is transactional but uses ordinary index creation, which can block writes; schedule it as an explicit maintenance operation. It does not modify content or refresh materialized titles. See [Title/](../src/Title/) for rebuild and ordering behavior.

## Rendering and HTTP hooks

Templates receive `$node` as a wrapper, with `children()`, `path()`, `meta`, and value properties. `$cms->render('downloads')` resolves a handle first and then a UID for embedded rendering.

[Cosray\Contract](../src/Contract/) contains `HttpGet`, `HttpPost`, `HttpPut`, and `HttpDelete`. Their argument-free hooks run on the node's own path before CMS defaults and take over content negotiation. Without a hook, GET renders the view or returns JSON for `Accept: application/json`; other methods answer `400`. `Cosray\Util\Form::body($request)` reads parsed, JSON, or urlencoded bodies where needed.

An injected [Node\View](../src/Node/View.php) renders the node's own view: `render($context)` returns a response and `output($context)` a string. `ViewContext::viewContext(Wrapper $node)` supplies additional template variables for both served and embedded rendering; explicit view context wins over hook defaults.

## Working copies

The panel saves edits to a published, renderable node into a working copy while it remains published. Visitors, listings, menus, and search keep reading live content. Unpublished and non-renderable nodes are edited directly. Content, handle, and URL paths are drafted; visibility is live.

Publishing writes the working copy live and removes it. Discarding removes it without changing live content. Unpublishing folds pending changes into the live row. A working copy exists only while it differs from a published, renderable, undeleted node.

- `$cms->node->working($uid)` reads the working copy; `/preview/{uid}` renders it with panel access checks and the same GET dispatch.
- `Node\Store::save()` is a direct API write and does not update an existing working copy. Publishing that copy later can supersede it.
- `draft()`, `publish()`, `discard()`, `unpublish()`, and `publishDraft()` select explicit working-copy transitions.
- `meta->draft` describes pending changes; Finder's `changes` filter selects them.
- Duplication copies the live version. Draft references are indexed separately; draft history survives publishing or discarding.

See [Node\Store](../src/Node/Store.php), [Node\Drafts](../src/Node/Drafts.php), and [panel draft tests](../tests/End2End/PanelEditorDraftTest.php) for transition details.

## Menus

`$cms->menu('main')` returns an iterable [Finder\Menu](../src/Finder/Menu.php); `html($class, $tag)` renders escaped nested markup. An existing empty menu renders nothing; an unknown handle raises an error.

Items link to nodes (`node`), literal localized URLs (`url`), or catalog assets (`asset`); other types render as labels. Node links follow the node's current localized path and inherit its title unless overridden. The `children` type expands published, visible children dynamically, with node queries per configured item and depth. Hidden parents remove their subtree from ordinary output. Locale maps follow configured fallbacks and neutral `zxx` values.

[Cosray\Menus](../src/Menus.php) is the write API. It enforces same-menu parents, rejects cycles, supports subtree removal, and synchronizes reference indexes. `place()` uses an exact sibling index; `move()` uses loose sort positions. Menu depth limits constrain authoring rather than rendering. Writes accept an optional `Cosray\Actor`, defaulting to the system user. The panel area needs `edit-menus`; creating, deleting, or renaming a menu additionally needs `manage-menus`.
