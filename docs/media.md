# Media

Cosray stores uploads in a shared asset catalog. Fields reference catalog items by UID rather than owning a separate copy of each file. The implementation is in [Assets/](../src/Assets/), [Controller\Media](../src/Controller/Media.php), and the media [Value classes](../src/Value/).

## Stored values and imports

Image, file, and video values contain locale maps of `{uid, meta?}` lists. Per-use metadata can override catalog metadata; image `alt()`, `title()`, and `caption()` resolve those fallbacks. Serialized content includes an `assets` map for resolving UIDs into URLs and preview metadata.

[Assets\Ingest](../src/Assets/Ingest.php) catalogs bytes and a filename without an HTTP request or session. It shares upload validation, SVG sanitization, and hash deduplication with the HTTP endpoint; imports can pass an actor and initial metadata. Rejections throw `Cosray\Exception\IngestError`. Removing a field reference does not delete the asset.

### Legacy richtext imports

`Cosray\LegacyRichtext\Converter`, supplied by the transitional `cosray/legacy-richtext-converter` dependency, converts legacy HTML for one-shot imports. It requires Node.js but not installed panel assets or panel npm dependencies. It is not a request-time HTML conversion API.

```php
use Cosray\LegacyRichtext\Converter;
use Cosray\Richtext\Normalizer;

$documents = new Converter()->convert([
    'intro:en' => '<p>Old <strong>content</strong></p>',
]);
$document = new Normalizer()->normalize($documents['intro:en']);
```

Normalize and validate converted documents before persistence when the surrounding migration does not already do so. See [richtext storage](richtext-format.md) for the envelope and migration requirements.

## URLs and image sizes

Originals live below `{path.public}{path.assets}/{uid[:2]}/{uid}/{slug}`. The slug comes from the original filename; the catalog retains that original name. Identical bytes on the same disk reuse the existing asset.

Renditions live below `{path.public}{path.cache}/{uid[:2]}/{uid}/`. The web server serves existing originals and renditions directly. A PHP fallback generates a missing named rendition atomically, after which the web server can serve it normally. URLs include `app.url_prefix`; see [path configuration](application.md#filesystem-directories-and-url-paths).

Configure sizes by name:

```php
'media.sizes' => [
    'card' => ['crop' => [600, 400], 'quality' => 80],
    'article' => ['width' => 1200],
],
```

Each definition has one mode: `width`, `height`, `long-side`, `short-side` (positive integer), or `crop`, `fit`, `resize` (width/height pair). Options include `quality`, `enlarge`, and crop position `pos`. [Assets\Sizes](../src/Assets/Sizes.php) validates definitions and declares the built-in panel and block sizes.

Templates call `$image->size('card')`. SVGs and other non-resizable assets keep their original URL. Unknown size names, missing assets, and stale filenames do not generate arbitrary renditions. Existing files are not regenerated merely because a size definition changes; remove the affected cached renditions deliberately when deploying such a change.

Block image ladders, responsive `sizes`, gallery ratios, and spacing are documented in [Blocks](blocks.md#rendering).

## HTTP integration

Editor elements should use `window.Cosray.upload(type, file)`, which supplies the session's CSRF token; see the [bridge](controls.md#the-windowcosray-bridge). Custom integrations should inspect [Routes](../src/Routes.php) and [Controller\Media](../src/Controller/Media.php) for current access checks and request/response fields.

The current endpoint groups are:

- `POST /media/{mediatype}`: multipart upload under `file`, for image, file, or video.
- `GET /media/library`: paged catalog search, kind/date filters, and batch UID lookup.
- `GET /media/{uid}`: asset metadata and usage.
- `PUT /media/{uid}`: editable catalog metadata.
- `DELETE /media/{uid}`: deletion when unreferenced; referenced assets return `409` with usage information.

The library's filter vocabulary splits catalog files into audio and documents. Missing catalog entries render no media; old owner-scoped asset URLs are not supported by the catalog routes.

## Reference indexes

Two derived tables track ownership:

- `asset_references`: owner type/UID to asset UID, with a restrictive foreign key protecting asset deletion.
- `node_references`: owner type/UID to target node UID.

[References\Scanner](../src/References/Scanner.php) walks media, reference fields, typed rows, and richtext carriers (`image.uid`, `link.asset`, `link.node`). Live node writes, working copies, and menu writes synchronize their references. Node backlinks are informational, not a general deletion guard; parent/child deletion has separate rules.

Use the app's `php run db:references` after direct imports or migrations that bypass normal writers. It rebuilds the derived indexes, not the content. Inspect [References/](../src/References/) for the owner types covered by the current rebuild before adding another content owner.

## Migrating pre-catalog content

Migration `000000-000019` catalogs legacy files, moves them into the pool layout, rewrites content, and writes `asset-legacy-map.json`. Migration `000000-000020` uses that mapping for legacy richtext links/images and writes `richtext-migration-report.json`. Review unresolved references and lossy conversions before relying on the migrated content. These are migrations for existing installations, not steps to rerun after every update.
