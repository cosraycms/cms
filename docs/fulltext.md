# Full-text search

Cosray provides PostgreSQL full-text search for public websites through `Nodes::fulltext()`. Applications supply their own search page or endpoint. Existing panel collection searches, reference pickers, `search()` and `searchTitle()` remain substring searches.

## Select the content

Nothing is indexed automatically, including conventional title fields and implementations of `Title`. Opt in with an explicit A–D weight:

```php
use Cosray\Contract\Title;
use Cosray\Field\Blocks;
use Cosray\Field\Text;
use Cosray\Field\Textarea;
use Cosray\Schema\Fulltext;
use Cosray\Schema\FulltextWeight;
use Cosray\Schema\Route;
use Cosray\Schema\Translate;

#[Route('/articles/{uid}')]
final class Article implements Title
{
    #[Translate]
    protected Text $name;

    #[Fulltext(FulltextWeight::B), Translate]
    protected Textarea $summary;

    #[Fulltext(FulltextWeight::D), Translate]
    protected Blocks $content;

    #[Fulltext(FulltextWeight::A)]
    public function title(): string
    {
        return $this->name->value()->unwrap();
    }
}
```

Supported field classes are exactly `Text`, `Textarea`, `RichText`, `Blocks` and `Entries`. Custom field classes, including custom `Text` subclasses, need explicit extraction support before they can be opted in. Code, iframe markup, provider ids, references, and media are not prose fields.

- An annotated `Blocks` or `Entries` field supplies its weight to supported descendants. A child annotation overrides that weight; `#[Fulltext(false)]` excludes a field or a structural subtree. Unsupported descendants are skipped, never flattened as arbitrary JSON.
- An unannotated structural field still discovers explicitly annotated children, but supplies no inherited weight. Only allowed row types contribute. The existing restrictions on nested repeaters apply.
- Embedded fields use their ordinary flat stored names. The embedding property itself is not a full-text container.
- `#[When]` deactivation excludes dormant values. Conditions read neutral-locale siblings in the owning node or row, not similarly named fields elsewhere.
- Rich text is read from its structured document, without rendering templates or loading assets. Adjacent marked text runs stay adjacent; paragraphs, list items and hard breaks retain word boundaries. Link destinations, image metadata, row identities, layout and arbitrary JSON keys never contribute.
- Both the vector and the snippet source contain only selected text. Do not opt private fields into this shared index; panel-only fields are not supported in v1.

An annotated `title()` contributes only when it is the resolved `Title` provider: an outer implementation wins over an embedded one, and an explicitly selected embedded provider wins over automatic candidates. Inherited concrete implementations retain their annotations. Arbitrary method annotations are unsupported; the `Title` interface itself does not opt classes in.

Computed titles come from the materialized `nodes.title` map. A save uses the map it just materialized; a rebuild reads the column without hydrating nodes or calling `title()`. An empty effective title contributes nothing and is reported by the rebuild. A field-based title is selected through its field annotation instead. Opting in both a computed title and an identical backing field deliberately contributes twice; equal strings are not deduplicated.

## Locales and language analysis

Configure `pgDict` when registering content locales:

```php
$locales->add('en', 'English', pgDict: 'english');
$locales->add('de', 'German', fallback: 'en', pgDict: 'german');
```

`pgDict` names a built-in PostgreSQL text-search **configuration**, not an arbitrary dictionary. Migration `000000-000036` copies the server's built-in configurations, including `simple`, into the CMS namespace as `fts_<name>`, with `unaccent` before their dictionaries. Table-prefix installations use the corresponding object prefix. Provisioning runs only in migrations, never on a save or query.

Omitting `pgDict` selects the derived `simple` configuration: accent-insensitive matching without stemming or stopword removal. An explicit unavailable name is an error, never a silent fallback. Configurations are loaded and validated lazily before live writes, searches and rebuilds; boot, CLI help and migration commands do not require the new objects to exist yet. Even an empty rebuild validates its locales.

Each node has one effective document per configured content locale. Values resolve through the requested locale, its configured fallbacks, then neutral `zxx`, rather than combining all translations. Shared and asymmetric block lists follow the [blocks translation rules](blocks.md#translation). Computed titles use the same stored-title fallback chain as listings.

The **target locale's analyzer processes the whole effective document**, including fallback prose. German analysis of fallback English text does not provide English stemming or translate a German query into English. Displayed-content fallback is the v1 policy, but remains a search-quality review topic.

Indexing, querying and highlighting use the same configuration. For example, `cafe` matches `Café` and the highlight retains `Café`. This does not promise every language-specific spelling equivalence, transliteration, synonym or typo correction.

## Query and render results

```php
$results = $cms->nodes()
    ->types(Article::class)
    ->fulltext($query)
    ->limit(20)
    ->offset($offset);

$total = $results->count();

foreach ($results as $node) {
    $path = $node->path();
    $score = $node->meta->search->score;
    $snippet = $node->meta->search->snippet;
}
```

`fulltext()` composes with type, field and hierarchy filters. It matches only the current locale's effective document, so fallback content does not duplicate results. `count()` ignores pagination, uses the same eligibility conditions, and never computes snippets. Repeated calls to `fulltext()` before fetching replace the query.

Input is bound directly to PostgreSQL's `websearch_to_tsquery`, not interpolated into SQL or passed through the Finder DSL:

| Input         | Meaning                                                      |
| ------------- | ------------------------------------------------------------ |
| `red fox`     | Both terms are required                                      |
| `"red fox"`   | A phrase, including the analyzer's stopword positions        |
| `red OR blue` | Either alternative                                           |
| `red -blue`   | Red without blue                                             |
| `-blue`       | Documents not matching blue; potentially broad and expensive |

Empty, punctuation-only and stopword-only queries return no results. With `simple`, words such as `the` are not stopwords. Negative-only and broadly negated expressions retain PostgreSQL semantics; they can scan much of the index and should be considered when an application sets rate limits. Prefix matching and typo tolerance are not included. `fulltext` remains an ordinary content-field name in the Finder DSL.

Ranking uses `ts_rank_cd`, PostgreSQL's default A–D weights (`1.0`, `0.4`, `0.2`, `0.1`), and normalization `0`. Results default to descending relevance, then ascending node UID for ties. An explicit `order()` overrides relevance ordering. The score is relative to this query, not a percentage or comparable across unrelated searches.

Contributions keep document order and are joined with PostgreSQL's vector concatenation operator. A phrase can span two contributions, such as the end of a title and the start of a summary, just as it can span sentences inside one text. The snippet source keeps a separator at each contribution boundary.

### Public eligibility

By default, results must be published, visible and undeleted, and have an active public URL in the requested locale or its fallback chain. The node's current type must be routable. Flags and paths are read at query time; changing them does not depend on a vector rebuild.

`fulltext()` inherits the Finder's existing `published()`, `hidden()` and `deleted()` settings. Its active-URL requirement is a separate `activeUrl()` toggle, on by default during full-text search. `activeUrl(false)` removes the URL/routability gate. Server-owned code can explicitly relax gates for an authorized non-public consumer:

```php
$internal = $cms->nodes()
    ->fulltext($query)
    ->published(null)
    ->hidden(null)
    ->activeUrl(false);
```

This is not an authorization API. Never let unrestricted request parameters choose those gates. The index also contains selected text from unpublished, hidden and non-routable live nodes; the table is not a public database view. Soft-deleted nodes lose their index rows.

### Safe snippets

`$node->meta->search` is a readonly `Cosray\Fulltext\Search` value with a float `score` and a `Cosray\Fulltext\Snippet`. It belongs to this fetched result, not the node's content or schema. Ordinary fetches have no search value; test with `isset($node->meta->search)`.

The snippet exposes:

- `segments`: ordered arrays containing `text` and boolean `match`.
- `text()`: plain text without highlight markers.
- `html()`: escaped text with fixed `<mark>` tags around matches.

In an escaped Boiler template, deliberately unwrap that safe HTML:

```php
<a href="<?= $node->path() ?>"><?= $node->title() ?></a>
<p><?= $this->unwrap($node->meta->search->snippet->html()) ?></p>
```

Do not output raw PostgreSQL headline text as HTML. Source control characters used as headline markers are stripped during extraction; every resulting segment is escaped before markup is added. Source spelling and accents are retained. PostgreSQL may omit HTML-like tags when constructing a headline; this is not an HTML-preservation API.

The fixed headline settings are `MaxWords=35`, `MinWords=15`, `MaxFragments=2`, with `…` between fragments. These are word/fragment bounds, not a byte-size limit. Headlines are part of the find statement but not its ordering or grouping. With pagination PostgreSQL evaluates them for limit plus offset rows; an integration `EXPLAIN` check guards that plan shape. Unlimited queries generate one headline per match. Use a limit and avoid unnecessarily large offsets.

The placement under `meta->search` remains a v1 working API scheduled for review after real template usage; score and snippet access are already available.

## Synchronization and maintenance

Normal writes through `Node\Store` replace all locale documents inside the content transaction. Indexing failure rolls back the write and index together, including inside an outer transaction. No worker or asynchronous queue is involved.

| Change | Index behavior |
| --- | --- |
| Create or save live content, published or not | Replace selected documents atomically |
| Save or discard a working copy | Keep live documents unchanged |
| Publish a working copy | Index newly live content |
| Unpublish without a working copy | Keep documents; public queries exclude the node |
| Unpublish and fold in a working copy | Index folded live content; public queries exclude the node |
| Change visibility or active paths | Query current flags/paths immediately |
| Delete a node | Remove its full-text rows in the deletion transaction |

`php run db:fulltext` is registered by the app's `Cosray\Console\Commands`, not the library's request-free test runner, which has no application schemas. The command resolves the configured node classes, fields, block registry and locales. It needs no HTTP request or node rendering.

The rebuild scans bounded batches of node keys up to a starting high-water mark. For each node it locks the row before reading live content, replaces its documents in a short transaction, and releases the lock before the next node. A concurrent live writer and rebuild cannot overwrite one another with an earlier scan snapshot. If a caller wraps the rebuild in its own transaction, savepoints preserve that transaction, but its locks necessarily last until the caller ends it.

Reports count processed nodes, indexed nodes, empty/removed nodes, failures, and missing effective locale titles. Missing schemas and extraction errors are failures, not guesses; the affected node's previous index is preserved and other nodes continue. An incomplete command exits nonzero and identifies failed nodes without dumping their content. Rerunning is safe. The rebuild does not update editorial content, timestamps or history and does not disable history triggers.

Changing selections, weights, title logic, locales, fallbacks or analyzer behavior requires rebuilding existing nodes. Removed selections/locales disappear on the next synchronization and across known schemas on rebuild. Direct imports or migrations bypassing `Node\Store` require a rebuild too; database triggers do not maintain this index. Low-level `Fulltext\Sync::replace()` callers must hold the node row lock and provide the corresponding live content/title snapshot inside their transaction.

Computed titles may depend on other nodes, external state or time. There is no dependency tracking or scheduled invalidation: the indexed title stays materialized until its owner is saved or titles are rebuilt. Run `db:titles` **before** `db:fulltext` after changing title logic or when titles are missing.

### Deployment order

1. Coordinate a maintenance window: stop old writers and keep searches disabled while schema, selection or analysis policies differ. Row locking does not make concurrent workers with different policies safe.
2. Deploy the code and explicit schema opt-ins. Apply `php run db:migrations --apply` from the application; migration `000000-000036` adds `source`, configurations and vector construction. Fresh installs include them already. The migration role must be able to create these objects and use the installed `unaccent` extension.
3. Inspect any existing full-text rows rather than assuming the old stub populated nothing. Vectors cannot reconstruct source prose: a rebuild is required even if old vectors exist.
4. Run `php run db:titles` when title materialization needs refreshing, then `php run db:fulltext`. Resolve reported failures and rerun before exposing search.
5. Resume normal writes and public search only after the complete rebuild succeeds. During an ordinary rebuild, completed and pending nodes can coexist; plan policy changes accordingly.

Cosray does not truncate selected documents to make indexing succeed. PostgreSQL's 1 MB vector limit fails the write with the node UID and contributing field named. PostgreSQL also clamps word positions above 16383, degrading phrase/rank behavior late in very long documents, and ignores individual lexemes longer than 2047 bytes; these server limits are not detected or repaired by Cosray.

## Deferred work

Panel FTS, working-copy search, panel-only fields with a separate vector/source, class-level defaults, prefix/autocomplete, typo tolerance, attachment extraction and ranking customization are not part of v1. Public extraction and storage are shared machinery, not panel permissions. Displayed-content fallback and the `meta->search` placement are explicit follow-up review topics.
