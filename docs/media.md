# Media

Cosray stores uploads in a shared asset catalog. Fields reference catalog items by UID rather than owning a separate copy of each file. The implementation is in [Assets/](../src/Assets/), [Controller\Media](../src/Controller/Media.php), and the media [Value classes](../src/Value/).

## Stored values and imports

Image, file, and video values contain locale maps of `{uid, meta?}` lists. Per-use metadata can override catalog metadata, as described under [per-use texts](#per-use-texts). Serialized content includes an `assets` map for resolving UIDs into URLs and preview metadata.

[Assets\Ingest](../src/Assets/Ingest.php) catalogs bytes and a filename without an HTTP request or session. It shares upload validation, SVG sanitization, and hash deduplication with the HTTP endpoint; imports can pass an actor and initial metadata. Rejections throw `Cosray\Exception\IngestError`. Removing a field reference does not delete the asset.

### Location data

Originals are public, and a phone writes where a photo was taken into it unasked. Ingest removes the GPS block of EXIF and the GPS properties of XMP from JPEG, PNG, WebP and AVIF files; the rest of their metadata stays. Place names in text fields stay too, and so does XMP in a compressed PNG chunk. [Assets\Location](../src/Assets/Location.php) does this. `php run media:strip-location` lists the stored files that still hold location data, and with `--apply` it removes it, which cannot be undone without a backup.

### SVG uploads

SVG files are rebuilt from an allowlist before they are stored; see [Util\Svg](../src/Util/Svg.php) for the lists. Scripts, event handlers, `foreignObject`, comments, `<metadata>`, and editor data are removed. Links may only point into the document, except web and mail links on `<a>` and embedded PNG, JPEG, GIF, or WebP images on `<image>`. CSS may only reference document fragments and embedded images or fonts. Empty or malformed files, documents without an `svg` root in the SVG namespace, and files that declare their own DTD entities are rejected; exporting such a file again from the editor usually fixes it.

Copyright and license information in removed comments or `<metadata>` is not kept, and only the sanitized file is stored. Legal comments that start with `<!--!`, the form Font Awesome and SVGO use for license notices, are kept; notices before or after the `svg` element move inside it. When a license requires attribution that the file does not carry in such a comment, provide it on the site, and keep source files outside the CMS.

Embed uploaded SVGs by URL, for example with `<img>`, rather than inlining their markup. An image keeps its stylesheet to itself and loads nothing external; inlined markup shares the page's CSS, and a kept `<style>` element would apply to the whole page. [Icons](application.md#icons) are the inlined exception and are sanitized more strictly.

The web server serves originals directly, so configure a restrictive policy for SVG responses there as a second line of defense. For example, in a Caddyfile:

```caddyfile
@svg path *.svg
header @svg Content-Security-Policy "default-src 'none'; style-src 'unsafe-inline'; img-src data:; font-src data:; sandbox"
header @svg X-Content-Type-Options nosniff
```

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

## Per-use texts

Each text has one job:

| Text | Purpose | Shape |
| --- | --- | --- |
| Alt text | Describes an image for screen readers | Per language |
| Caption | Shown with an image or video | Per language, a [small richtext](richtext-format.md#captions) |
| Attribution | Names who made an image | One neutral string, as names do not translate |
| Title | A file's display name | Per language |

An editor can set each one per use, and an empty per-use text falls back to the value in the media screen, which the details dialog shows as a placeholder. Images no longer offer a title: a stored one stays, still fills in for a missing alt text, and a real alt text overrides it.

A plain image field offers only the alt text. Declare the others on the field when its template prints them:

```php
use Cosray\Field\Image;
use Cosray\Schema\Attribution;
use Cosray\Schema\Caption;
use Cosray\Schema\Label;

#[Label('Cover'), Caption, Attribution]
public Image $cover;
```

An attribute decides what editors can fill in, not the markup. A template prints what it asks for:

```php
<?= $page->cover ?>                          // <img>, the same as img()
<?= $page->cover->size('card')->img('photo', loading: 'lazy') ?>
<?= $page->cover->size('block')->img(
	srcset: $page->cover->srcset('block-sm', 'block', 'block-lg'),
	sizes: '100vw',
) ?>
<?= $page->cover->linked() ?>                // <a><img></a> with a link, <img> without
<?= $page->cover->figure() ?>                // <figure> with link, caption and attribution
<?= $page->photos ?>                         // an <img> per image
<?= $page->photos->gallery() ?>              // a wrapper with a <figure> per image
```

`figure()` prints what `linked()`, `caption()` and `attribution()` return, whichever attributes the field declares, so values from the media screen count. Without text it still prints the `<figure>`, so pages keep the same structure. The attribution sits in a `<small>`. Pass your own image or a class with `$page->cover->figure($page->cover->img('photo'), 'cover')`.

`img()` prints root-relative URLs and the `width` and `height` of the rendition, so the browser keeps the space while the image loads; `url()` gives an absolute URL. Further named arguments become attributes and can replace those: `img(loading: 'lazy')`, `img(width: null)` or `img(...['data-zoom' => '2'])`. It needs `height: auto` from a stylesheet wherever CSS changes an image's width, as it does for any image with these attributes.

`gallery()` prints this, with the ratio and crop an editor chose for the gallery:

```html
<div class="cms-image-gallery" data-ratio="4/3" style="--ratio: 4/3" data-crop>
    <figure class="cms-image-gallery-item">
        <img src="/cache/…/pic-block-thumb.jpg" data-url-large="/cache/…/pic-large.jpg" width="480" height="320" alt="…">
        <figcaption>Words <small>© Jane Doe</small></figcaption>
    </figure>
</div>
```

`gallery(size: 'thumb', large: 'large', prefix: 'site')` picks the [named sizes](#urls-and-image-sizes) and starts the classes with `site`; `data-url-large` is only there with `large`, for a lightbox. Further named arguments go to each `<img>`. Its images load lazily unless you pass `loading: null` or another value, while `img()` and the image block leave that to you, as a lazy hero image delays the page's main picture. For your own grid, loop over the images: `foreach ($page->photos as $photo)` and print `$photo->size('thumb')->figure()`.

`#[Link]` lets an editor link each of a field's images to an address, a page or a file. A link belongs to the use, like a crop, and is one per use rather than per language; a page link follows the visitor's language.

```php
#[Label('Cover'), Link]
public Image $cover;
```

```php
<?= $page->cover->linked($page->cover->img('cover')) ?>
<?= $page->cover->link() ?>
```

`linked()` puts the content, by default the image's tag, inside the `<a>` with the link's `target` and `rel`, and returns it alone without a link or when the target is gone. `link()` returns only the address. An address is relative or uses `http`, `https`, `mailto` or `tel`, like a [richtext link](richtext-format.md#links), and gets the same `rel`.

`#[Alt]`, `#[Caption]`, `#[Attribution]` and `#[Link]` take `required: true`, which asks the same of every image in the field:

```php
#[Label('Cover'), Required, Alt(required: true), Attribution(required: true)]
public Image $cover;
```

Saving refuses an image without it, like a required field, also for a working copy. A field without an image passes, so `#[Required]` still decides whether there has to be one. A value from the media screen counts, except for a link, which belongs to the use. A translated text has to exist in the default language, and an asymmetric field only asks this of its list in the default language. A later edit in the media screen is not checked against the pages that rely on it. `#[Alt]` without the argument changes nothing, since every image field offers an alt text.

`#[Caption]` also applies to video fields. Without an attribute the input is hidden, nothing stored is lost, and the methods still return stored and catalog values. `caption()` returns escaped HTML as `Cosray\Markup`, which templates print as is; `captionText()` gives plain text.

## Focal point and crop

The details dialog sets both on the picture itself. A focal point has a default in the media screen that a use can override; a crop belongs to the use alone. Both are fractions of the image as displayed, so they hold for every size and for a rotated JPEG, and the focal point is relative to the whole image, so another crop keeps it:

```json
{
	"uid": "...",
	"meta": {
		"focal": { "x": 0.31, "y": 0.2 },
		"crop": { "x": 0.1, "y": 0.05, "w": 0.8, "h": 0.6 }
	}
}
```

Neither touches the original. A crop applies to every URL of the image: `$image->url()` gives the crop at full resolution, and `$image->size('card')` crops first and then sizes. A focal point decides what a size in `crop` mode keeps, overriding its `pos`. For an image cut by CSS, `$image->position()` gives the `object-position` declaration, or an empty string without a point; `tag()` and the image blocks print it. The window moves by the meaning of `object-position` in all of these, so the dialog's previews, CSS and renditions agree.

SVGs have no renditions, so they get a focal point but no crop. GIFs do not offer a crop either, as an animated one only survives as a copy of its original.

A crop is presentation, not redaction. The original stays public at its asset URL, which a template can pass on with `img(...['data-original' => $image->publicPath()])` for a lightbox. To remove something from a picture, edit the file and upload it again.

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

Each definition has one mode: `width`, `height`, `long-side`, `short-side` (positive integer), or `crop`, `fit`, `resize` (width/height pair). Options include `quality`, `enlarge`, crop position `pos`, and `format`, which writes the rendition as `jpeg`, `png`, `webp` or `avif` instead of the original's format. A GIF stays a GIF, and a transparent image turned into a JPEG sits on white. [Assets\Sizes](../src/Assets/Sizes.php) validates definitions and declares the built-in panel and block sizes. Reading or writing AVIF takes a build of GD that has it; where it is missing, the URLs redirect to the original.

Templates call `$image->size('card')`. SVGs and other non-resizable assets keep their original URL. Unknown size names, missing assets, and stale filenames do not generate arbitrary renditions.

A rendition's name carries a fingerprint of its size's definition: `{stem}-{size}~{fingerprint}{ext}`. A changed definition leads to new URLs and new files, so nothing needs clearing when it is deployed. The old files stay behind in the cache directory, where they do no harm and can be removed at any time.

A use's crop or focal point adds a variant to the rendition's name: `{stem}-{size}~{fingerprint}~{recipe}~{signature}{ext}`. The signature is an HMAC with `app.secret`, so only URLs the server minted generate files, and tampering answers 404. A focal point only changes the name of a size in `crop` mode, so other sizes keep sharing one file. Changing a crop or focal point leaves the old rendition behind in the asset's cache directory, where it does no harm. Without `app.secret` there are no variants: images stay whole, sizes in `crop` mode ignore the focal point, and the dialog offers no crop.

A JPEG rendition of a JPEG keeps the original's colour profile, so a photo in Adobe RGB or Display P3 does not come out muted.

GD renders images by default. `'media.backend' => 'imagick'` renders them with ImageMagick where the `imagick` extension is there, which keeps the colour profile in WebP and AVIF too, and decodes a JPEG only as far as a rendition needs. Changing it leads to new rendition names, as a size's fingerprint includes it. Without the extension every rendition fails with an error.

With GD, resizing decodes the whole original, at about 5 bytes per pixel, so `memory_limit` bounds what can be resized: 128M takes about 25 megapixels, a 45 megapixel photo needs about 256M. A larger original gets no renditions: their URLs redirect to the original, which is heavier but still shows, uncropped, and the failure is logged. A crop costs no extra memory beyond its rendition, which is copied directly out of the decoded original.

Block image ladders, responsive `sizes`, gallery ratios, and spacing are documented in [Blocks](blocks.md#rendering).

## HTTP integration

Editor elements should use `window.Cosray.upload(type, file)`, which supplies the session's CSRF token; see the [bridge](controls.md#the-windowcosray-bridge). The upload is `POST /media/{mediatype}`, a multipart request with the file under `file`, for image, file, or video; [Routes](../src/Routes.php) and [Controller\Media](../src/Controller/Media.php) hold its access checks and response fields.

Browsing, editing and deleting catalog entries happen in the panel's media screen, whose server-rendered picker the editor controls embed; [Assets\Library](../src/Assets/Library.php) holds the listing and deletion rules. Its search covers file names and the catalog's texts, and it narrows to files nothing uses or images without an alt text in the catalog, whatever their uses set themselves. Several files can be selected and deleted at once, and those in use stay. Replacing a file keeps its uid, so every use shows the new one and keeps its crop and focal point as fractions of the new image. It moves to a new address even under the same name, so no cache keeps answering with the old file. The [API](api.md#assets) reads, lists and uploads catalog entries. The library's filter vocabulary splits catalog files into audio and documents. Missing catalog entries render no media; old owner-scoped asset URLs are not supported by the catalog routes.

## Reference indexes

Two derived tables track ownership:

- `asset_references`: owner type/UID to asset UID, with a restrictive foreign key protecting asset deletion.
- `node_references`: owner type/UID to target node UID.

[References\Scanner](../src/References/Scanner.php) walks media, reference fields, typed rows, an image's link, and richtext carriers (`image.uid`, `link.asset`, `link.node`). Live node writes, working copies, and menu writes synchronize their references. Node backlinks are informational, not a general deletion guard; parent/child deletion has separate rules.

Use the app's `php run db:references` after direct imports or migrations that bypass normal writers. It rebuilds the derived indexes, not the content. Inspect [References/](../src/References/) for the owner types covered by the current rebuild before adding another content owner.

## Migrating pre-catalog content

Migration `000000-000019` catalogs legacy files, moves them into the pool layout, rewrites content, and writes `asset-legacy-map.json`. Migration `000000-000020` uses that mapping for legacy richtext links/images and writes `richtext-migration-report.json`. Review unresolved references and lossy conversions before relying on the migrated content. These are migrations for existing installations, not steps to rerun after every update.
