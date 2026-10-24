# API

Cosray's JSON API serves headless frontends and integrations: a frontend reads published content, often without a token, and an integration reads every state with a token. Both use the same endpoints; [node permissions](application.md#node-permissions) decide what a client sees. The implementation lives in [Api/](../src/Api/).

## Enabling it

The API is off until a site mounts it:

```php
$app = App::create($root, [
    'api.path' => '/api',
]);
```

Node paths at or below `api.path` are refused when saved, like the panel's. `app.url_prefix` applies as for every route: with `/site`, the API answers below `/site/api`.

## Requests and responses

Clients authenticate with an [API token](application.md#api-tokens) in `Authorization: Bearer <token>`; the API reads no cookies. A request without the header is anonymous. A header that is not a bearer token, or a token that is unknown, revoked or expired, answers `401` instead of falling back to anonymous access.

Without a token, a client reads published nodes of routable types: what the website shows. With a token, it reads what its user's roles allow. A node the client may not read answers `404`, as if it did not exist.

Responses are JSON. Errors are [problem details](https://www.rfc-editor.org/rfc/rfc9457) with `Content-Type: application/problem+json`, `status`, `title` and usually a `detail`; invalid content adds `errors`. `400` means a malformed request, `401` a token that does not authenticate, `404` an unknown endpoint or a node the client may not read, `503` an unreachable database, with `Retry-After`. A site's own `error.renderer` does not change them.

`?locale=` selects the locale values resolve in, through its fallbacks; it defaults to the default locale, and an unknown locale answers `400`.

## Endpoints

| Endpoint | Answers with |
| --- | --- |
| `GET /api` | `defaultLocale`, `locales` with their fallbacks, the `types` the client reads, and the `user` its token acts as |
| `GET /api/types` | The readable types, each with `route`, allowed `children`, and the `actions` the client may take |
| `GET /api/types/{type}` | One type with its field schema and defaults |
| `GET /api/nodes` | A list of nodes; see [listing nodes](#listing-nodes) |
| `POST /api/nodes/query` | The same list, with the parameters in a JSON body |
| `GET /api/nodes/{uid}` | One node; takes `view`, `fields` and `deleted` |

The field schema lists every field with its `name`, its `type` (the field class as a name: `RichText` reads `rich-text`), `label`, `description`, `required`, `translate` and `translateMode`, and the `options`, `limit`, `syntaxes` or `columns` it declares. Blocks and entries fields add their `rows` with each row type's fields. `default` is the value a new node starts with, in the [source view](#nodes).

## Nodes

A node carries `uid`, `type` (the type handle), `handle`, `parent` (a uid), `locale`, `title`, `path` (in the locale or its fallbacks), `paths` by locale, `published`, `hidden`, and `created`, `changed` and `deleted` as UTC RFC 3339 times. Paths leave out `app.url_prefix`. No user data appears.

The resolved view, the default, adds `fields`: every value resolved for the locale, ready to render. `view=source` adds `content` instead: the stored values of every locale, without the field class, in the shape writes accept. `fields=title,teaser` limits either view to the named fields; names a node lacks are skipped.

```json
{
	"uid": "k3x9aaqwt7mnb",
	"type": "article",
	"handle": null,
	"parent": null,
	"locale": "en",
	"title": "Our offer",
	"path": "/offer",
	"paths": { "en": "/offer", "de": "/angebot" },
	"published": true,
	"hidden": false,
	"created": "2026-10-01T08:00:00Z",
	"changed": "2026-10-20T14:12:09Z",
	"deleted": null,
	"fields": {
		"title": "Our offer",
		"intro": { "html": "<p>What we do.</p>" }
	}
}
```

### Resolved values

`Value::json()` gives each field's resolved value, so custom fields define theirs in their value class.

| Field | Value |
| --- | --- |
| Text, Textarea, Iframe | A string, empty when unset |
| RichText | `{html}`, rendered with internal links resolved |
| Code | `{code, syntax}` |
| Number, Decimal | A number or null |
| Checkbox | A boolean, or null for a nullable checkbox without a value |
| Option, Radio, Color | A string or null; colours as `#rrggbb` |
| Date, Time, DateTime | `YYYY-MM-DD`, `HH:MM`, or UTC RFC 3339; null when unset |
| Youtube | `{id, ratio}`, like `16/9`, or null |
| File, Video | `{uid, url, filename, mime, bytes, title, caption}`, or null |
| Image | The file members plus `alt`, `link`, `width`, `height`, `focal`, and `sizes`, a URL for every [named size](media.md#urls-and-image-sizes) |
| Files, Images | A list of those objects |
| Reference | A list of `{uid}` |
| Entries | A list of `{uid, type, fields}`, `type` naming the row type by handle |
| Blocks | `{columns, blocks}`; each block has `uid`, `type`, `layout`, `fields` and `meta`, a split `blocks` instead of `fields` |

URLs are root-relative and include `app.url_prefix`. An image the resize pipeline cannot process, such as an SVG, gives its original URL for every size. A file whose catalog entry is gone is left out.

## Listing nodes

`GET /api/nodes` answers with `items`, `total`, `limit` and `offset`. `POST /api/nodes/query` takes the same parameters as a JSON object, for uid lists and filters too long for a URL; lists are comma-separated in a query string and arrays in a body.

| Parameter | Meaning |
| --- | --- |
| `type` | Type handles |
| `uid` | Node uids, at most 1000; the default limit becomes their count |
| `handle` | Node handles |
| `parent` | The uid of the parent whose children to list |
| `published`, `hidden`, `deleted` | `true`, `false` or `any`; defaults `true`, `false` and `false` |
| `filter` | A [filter](#filters), JSON-encoded in a query string |
| `order` | Terms like `title`, `-changed` or `-start:date`; see below |
| `limit`, `offset` | 50 and 0 by default; at most 1000 per page |
| `fields`, `view` | As for a single node |

A list contains only nodes the client may read, whatever it asks for: anonymous clients never see an unpublished node.

`order` defaults to the title. A leading minus sorts descending; `:number`, `:date` and `:datetime` sort a content field by its type instead of as text. Builtins are `uid`, `handle`, `type`, `title`, `created`, `changed`, `published` and `hidden`; `position` follows the manual order among siblings. The uid breaks ties, so pages stay stable.

### Filters

A filter is an object whose members must all hold. A member maps a field to a value, meaning equality, or to operators:

```json
{
	"or": [
		{ "type": "global-topic", "tenant": "t1" },
		{ "type": "local-topic", "tenant": "t1", "tutorial": { "in": ["a", "b"] } }
	]
}
```

| Operator | Meaning |
| --- | --- |
| `eq`, `ne` | Equal, unequal; also against null |
| `lt`, `lte`, `gt`, `gte` | Comparisons |
| `in`, `nin` | In a list of strings or numbers, or not |
| `like`, `ilike` | SQL patterns with `%` and `_`, case-sensitive or not |
| `exists` | Whether a content field has a value; true or false |
| `null` | Whether a builtin or field is null; true or false |

`and` and `or` take lists of filters, `not` takes one. A content field compares its effective value in the request locale; `title.de` reads one locale. Builtins are `uid`, `handle`, `type`, `parent`, `published`, `hidden`, `created`, `changed`, `routable` and `renderable`; `path`, `path.<locale>`, `references` and `references.<field>` work as in the [query language](content.md#frontend-queries). Values are data and need no quoting; the filter compiles directly into Finder conditions. PHP code can use the same filters through `Finder\Nodes::match()`.
