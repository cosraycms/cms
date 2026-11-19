# Maintenance mode

Maintenance mode holds back changes while a site is updated, for example while migrations rebuild its database: by default it refuses writes and keeps serving reads, so visitors and API clients can go on reading. See [Maintenance](../src/Maintenance.php) and [Middleware\MaintenanceMode](../src/Middleware/MaintenanceMode.php).

## Switching

```bash
php run maintenance:on
php run maintenance:on --full --retry=300 --message="Back at 10 pm"
php run maintenance:status
php run maintenance:off
```

- `maintenance:on` refuses writes until `maintenance:off`. Running it again replaces the earlier settings.
- `--full` refuses reads too, for an update after which the old content cannot be read.
- `--retry` sets the seconds clients are told to wait in `Retry-After`, 60 by default.
- `--message` reaches API clients as the problem's `detail` and visitors on the [maintenance page](#maintenance-page).

The mode is the file `maintenance.file` names, `maintenance.json` in the project root by default, or `MAINTENANCE_FILE` in the environment. It is a file rather than a database row, so it holds while migrations change the database. Keep it out of version control. Where a deploy installs each release in a directory of its own, point `MAINTENANCE_FILE` to a path the releases share, so the release serving requests sees the mode the deploy turns on in the next one. The commands need to write in its directory; the web server only reads the file.

A file without valid content, such as one made with `touch`, turns the mode on as well: it refuses writes, says 60 seconds, and lets nobody pass.

## What is refused

While the mode is on, a request with `POST`, `PUT`, `PATCH` or `DELETE` gets `503 Service Unavailable` with `Retry-After`, on the website, in the panel and in the API; with `--full`, every request does. Below `api.path`, the answer is [problem details](api.md), and other JSON requests get the mode's `message` and `retry` as JSON; browsers get the [maintenance page](#maintenance-page). The refusals are not logged. Forms on the website are refused as well, so nothing is submitted into a database that is being changed.

Requests run no jobs after their response, and `queue:work` and `queue:listen` start none, as jobs change content; they stay queued and run once the mode ends. Console commands run as usual.

Paths in `maintenance.allow`, each with everything below it, are served as usual, for example a health check:

```php
'maintenance.allow' => ['/health'],
```

## Maintenance page

Browsers get the template `maintenance`, looked up like the [error pages](application.md#views-and-errors): in `error.views`, else the site's views directory, then Cosray's own [maintenance page](../resources/error/maintenance.php). Cosray's page names the site by `app.title`, says that it is being updated, shows the message, reloads itself after `Retry-After` and speaks English or German, by the request's `Accept-Language`. A site with its own `maintenance.php` replaces it; its template gets:

- `message`: the `--message`, or null.
- `retry`: the seconds for `Retry-After`.
- `since`: when the mode was turned on, as an ISO 8601 UTC time.
- `full`: whether reads are refused too.
- `lang`: `en` or `de`, the language Cosray's page would use.
- `title`, and `debug`, `env`, `request` and `exception` like the error pages.

Keep the template to itself: in full mode, a migration may be changing the database, so menus, nodes and other content may be unreadable. A template that fails is logged as a warning and replaced by Cosray's page. A site with an `error.renderer` renders maintenance too, as an `UnderMaintenance` error.

## Passing in the browser

`maintenance:on` prints a link like `https://example.com/panel/maintenance?bypass=…`. Opening it sets an HTTP-only cookie that lets the browser through every refusal, to check the site before the mode ends, and leads to the panel. The link needs `app.url` to be absolute. Each `maintenance:on` makes a new secret; the file keeps only its hash.

## Web server

Requests that never reach PHP, such as files the web server serves itself, are not affected. A web server can refuse writes on the same file before they reach PHP, for an application that does not know the mode yet, and show a static copy of the maintenance page with `error_page 503`; with nginx:

```nginx
set $maintenance "";
if (-f /var/www/example.com/shared/maintenance.json) { set $maintenance "on"; }
if ($request_method !~ ^(GET|HEAD|OPTIONS)$) { set $maintenance "${maintenance}-write"; }
if ($maintenance = "on-write") { return 503; }
```
