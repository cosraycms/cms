# API

Cosray's JSON API serves headless frontends and integrations: a frontend reads published content, often without a token, and an integration reads every state with a token. Both use the same endpoints; [node permissions](application.md#node-permissions) decide what a client sees. The implementation lives in [Api/](../src/Api/).

## Enabling it

The API is off until a site mounts it:

```php
$app = App::create($root, [
    'api.path' => '/api',
    'api.origins' => ['https://www.example.com'],
    'api.max_age' => 60,
]);
```

`api.origins` lists the origins whose browser scripts may call the API; `'*'` allows any. With listed origins every response varies on `Origin`, so a shared cache does not hand one origin's answer to another. The API reads no cookies, so it never allows credentials. `api.max_age` lets shared caches keep anonymous reads for that many seconds; without it they revalidate. Responses to requests with a token are never cached.

Node paths at or below `api.path` are refused when saved, like the panel's. `app.url_prefix` applies as for every route: with `/site`, the API answers below `/site/api`.

## Requests and responses

Clients authenticate with an [API token](application.md#api-tokens) in `Authorization: Bearer <token>`; the API reads no cookies. A request without the header is anonymous. A header that is not a bearer token, or a token that is unknown, revoked or expired, answers `401` instead of falling back to anonymous access.

Without a token, a client reads published nodes of routable types: what the website shows. With a token, it reads what its user's roles allow. A node the client may not read answers `404`, as if it did not exist.

Responses are JSON. Errors are [problem details](https://www.rfc-editor.org/rfc/rfc9457) with `Content-Type: application/problem+json`, `status`, `title` and usually a `detail`; invalid content adds `errors`. `400` means a malformed request, `401` a token that does not authenticate, `403` a write the token may not make, `404` an unknown endpoint or a node the client may not read, `409` a conflict such as a duplicate uid or handle, `412` a failed `If-Match`, `415` a body that is not JSON, `422` invalid content, and `503` an unreachable database, with `Retry-After`. A site's own `error.renderer` does not change them.

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
| `POST /api/nodes` | Creates a node; see [writing nodes](#writing-nodes) |
| `PUT /api/nodes/{uid}` | Creates the node under this uid or replaces its content |
| `PATCH /api/nodes/{uid}` | Applies a JSON Merge Patch |
| `DELETE /api/nodes/{uid}` | Soft-deletes the node; `?children=true` takes its descendants along |
| `GET /api/search` | Full-text search; see [search](#search) |
| `GET /api/route` | The node at a URL path, or where it moved; see [routing](#routing) |
| `GET /api/menus`, `GET /api/menus/{handle}` | Menu handles, and one menu's items as the website shows them |
| `GET /api/assets`, `GET /api/assets/{uid}`, `POST /api/assets` | The asset catalog; see [assets](#assets) |

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

### Syncing

`changedSince=2026-10-24T08:00:00Z` lists the nodes changed after that time and adds `removed`: up to 1000 nodes of the readable types, with `uid`, `type` and `changed`, that changed after it but no longer pass the state filters because they were unpublished, hidden or deleted. A client keeping a copy, such as a static site build, passes the latest `changed` it has seen and drops the removed ones. Times have whole seconds, so a node changed in the same second may come again.

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

## Writing nodes

Writes send JSON in the source view: `content` maps fields to their stored values without the field class, and `handle`, `parent`, `published`, `hidden` and `paths` set the node's members. The derived members of a read, such as `title` or `changed`, are ignored, so a node read with `view=source` can be written back as it is. Unknown members and unknown fields are refused. Writes are attributed to the token's user and go live; the response is the written node, in the view the request's `view` names, with its `ETag`.

```json
{
	"type": "article",
	"published": true,
	"paths": { "en": "/offer" },
	"content": {
		"title": { "value": { "en": "Our offer", "de": "Unser Angebot" } },
		"intro": {
			"value": {
				"en": { "format": "cosray-richtext", "version": 1, "doc": "…" }
			}
		}
	}
}
```

`POST /api/nodes` creates a node of the named `type` and answers `201` with a `Location`; it may choose the `uid`, which answers `409` when taken. Fields it leaves out start with their defaults, paths it leaves out are generated from the type's route. Creating needs the type's `create` permission.

`PUT /api/nodes/{uid}` creates the node when the uid is free, which saves a client that keys its own records by uid the round trips of reading, failing and creating. Otherwise it replaces the content: fields the body leaves out return to their defaults, while members it leaves out keep their values. Changing the type or the uid answers `409` or `400`.

`PATCH /api/nodes/{uid}` takes a [JSON Merge Patch](https://www.rfc-editor.org/rfc/rfc7396) as `application/merge-patch+json` or `application/json`: objects merge, `null` removes a member, and lists replace. `{"content": {"title": {"value": {"de": "Neu"}}}}` changes only the German title, `{"paths": {"de": null}}` drops the German path so it is generated again, and `{"published": false}` takes the node offline.

Replacing and patching need the type's `change` permission, deleting its `delete` permission; deleting a node with children answers `409` unless `?children=true`, which needs only the permission for the node itself. A locked node answers `409` to every write and to deleting, as does deleting a subtree that holds one; a deleted node answers `404`.

### Concurrent writes

Every node response carries an `ETag` that changes with each write. Send it as `If-Match` to have a write fail with `412` when the node changed since it was read; `If-None-Match: *` makes a `PUT` create only. Lists carry no tags.

An editor may hold a working copy of a published node in the panel. A `PATCH` applies to the live node and to that copy alike, so publishing it later does not undo the API's change; unpublishing folds the copy into the node, as the panel does. A `PUT` answers `409` while a working copy exists, because replacing the content would leave the copy behind.

## Search

`GET /api/search?q=…` runs a [full-text search](fulltext.md) in the request locale over the nodes with an active URL, ordered by relevance unless `order` names another order. It takes the listing parameters and adds `search` to each item: its `score` and a `snippet` of escaped HTML with the matches marked.

## Routing

`GET /api/route?path=/de/angebot` resolves a URL path the way the website does, so a headless frontend can route its own URLs: it answers `{"node": …}` with the published node at that active path, read in the path's locale unless `?locale=` names one, or `{"redirect": "/de/neu"}` when the path is one the node no longer uses. The website serves any published node with a path, whatever its type, unless the type declares a read permission of its own, and so does this endpoint; `404` means nothing answers there. The path is the node path, without `app.url_prefix`.

Requesting a page URL itself with `Accept: application/json` answers with the same node representation, unless the node implements `Cosray\Contract\Json` to compose a payload of its own; see [rendering and HTTP hooks](content.md#rendering-and-http-hooks).

## Menus

`GET /api/menus/{handle}` answers with the menu's `items` as the website renders them: hidden items left out and `children` items expanded into the nodes they list. Each item has `id`, `type`, `title`, `href`, the linked `node` uid, `target`, `image`, `class` and its `children`. `GET /api/menus` lists the handles with their descriptions.

## Assets

`GET /api/assets/{uid}` answers with a catalog entry: `uid`, `url`, `filename`, `kind`, `mime`, `bytes`, `width`, `height`, `alt`, `title` and `caption` in the request locale, `credit`, `focal`, and `sizes`. Anyone may read it, as its file is public anyway.

`GET /api/assets` lists the catalog, newest first, and takes `kind` (image, video, audio, document), `q` matching the filename, `since`, `limit` and `offset`; it needs `read-nodes`. `POST /api/assets` uploads the multipart field `file`, needs `edit-nodes`, and takes an optional `type` (image, video or file, read off the file otherwise) and `meta`, JSON with `alt`, `title` and `caption` maps and a `credit`. It answers `201` with the new entry, `200` with the existing one when the catalog already holds the same bytes, and `422` when the file is refused.

## Previews

A headless frontend can show an editor's unpublished working copy. Name its preview route, and the panel's preview opens it instead of the site's own `/preview` page:

```php
'api.preview' => 'https://www.example.com/preview?uid={uid}&token={token}&locale={locale}',
```

The panel fills in the node's `uid`, the content `locale`, and a `token` that is valid for an hour and for that node only. The frontend reads the working copy with `GET /api/nodes/{uid}?preview={token}`, published or not, without an API token; responses are never cached. A forged, expired or foreign token answers `401`. Tokens are signed with `app.secret` and stored nowhere; without the secret, the panel keeps the site's own preview. The frontend has to allow the panel to frame it.

## Webhooks

Webhooks tell other systems that content changed, such as a static site build or a cache in front of the API. They fire for every write, through the panel, the API or PHP code, once it is committed:

```php
'webhooks' => [
    ['url' => 'https://build.example.com/hook', 'secret' => $secret, 'events' => ['node.*']],
],
```

Events are `node.created`, `node.updated` for a live write, publishing and unpublishing included, `node.draft` when an editor's working copy is saved or dropped, and `node.deleted`; `events` takes names, `node.*` or `*`, the default. Each event is a `POST` of `{"id", "event", "at", "uid", "type"}` with `X-Cosray-Event` and `X-Cosray-Delivery` headers; with a `secret`, `X-Cosray-Signature` carries `sha256=` and the HMAC-SHA256 of the body. Read the node through the API for its new state.

A write that site code makes inside its own transaction is reported only if that transaction commits. Deliveries go out in parallel after the response was sent, with a two-second connection and five-second total timeout; under PHP-FPM and FrankenPHP the client does not wait for them. A failed delivery is logged as a warning and not retried. A hook that needs every change should therefore also sync with [`changedSince`](#syncing) now and then.

## Embedded editing

An application can frame the panel's editor for its own users, limited to the fields it needs. Allow its origin first; the panel answers every page with `Content-Security-Policy: frame-ancestors 'self'` and the origins listed here:

```php
'panel.embed' => ['https://admin.example.com'],
```

The application's server exchanges a panel user's API token for a link that opens a panel session once, within five minutes: `POST /api/handoff` with `{"next": "/node/{uid}?from=embed&fields=title,intro&lang=de,en"}` answers `{"token", "url", "expires"}`. The frame loads `url`, which logs the user in and follows `next`, a path inside the panel. The session cookie reaches the frame only when both applications share a site, like `admin.example.com` and `cms.example.com`, or when `session.options` sets `cookie_samesite` to `None`.

With `from=embed`, the editor renders without the panel's masthead and navigation, shows only the `fields` named and the locales in `lang`, and publishes on save. `/node/create/{type}?from=embed&content={json}` opens a new node with the given source values filled in. The frame posts `{type, uid}` to the allowed origins: `cms-dirty` and `cms-pristine` as unsaved changes come and go, `cms-ok` after a save or, with `created: true`, after creating, and `cms-cancel` when the user cancels or presses Escape outside an open menu or dialog.
