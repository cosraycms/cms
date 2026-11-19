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
- `--message` reaches API clients as the problem's `detail` and the site's error page as the error's payload.

The mode is the file `maintenance.file` names, `maintenance.json` in the project root by default, or `MAINTENANCE_FILE` in the environment. It is a file rather than a database row, so it holds while migrations change the database. Keep it out of version control. Where a deploy installs each release in a directory of its own, point `MAINTENANCE_FILE` to a path the releases share, so the release serving requests sees the mode the deploy turns on in the next one. The commands need to write in its directory; the web server only reads the file.

A file without valid content, such as one made with `touch`, turns the mode on as well: it refuses writes, says 60 seconds, and lets nobody pass.

## What is refused

While the mode is on, a request with `POST`, `PUT`, `PATCH` or `DELETE` gets `503 Service Unavailable` with `Retry-After`, on the website, in the panel and in the API; with `--full`, every request does. Below `api.path`, the answer is [problem details](api.md); elsewhere, the site's error page renders it as an `UnderMaintenance` error, which is not logged. Forms on the website are refused as well, so nothing is submitted into a database that is being changed.

Requests run no jobs after their response, and `queue:work` and `queue:listen` start none, as jobs change content; they stay queued and run once the mode ends. Console commands run as usual.

Paths in `maintenance.allow`, each with everything below it, are served as usual, for example a health check:

```php
'maintenance.allow' => ['/health'],
```

## Passing in the browser

`maintenance:on` prints a link like `https://example.com/panel/maintenance?bypass=…`. Opening it sets an HTTP-only cookie that lets the browser through every refusal, to check the site before the mode ends, and leads to the panel. The link needs `app.url` to be absolute. Each `maintenance:on` makes a new secret; the file keeps only its hash.

## Web server

Requests that never reach PHP, such as files the web server serves itself, are not affected. A web server can refuse writes on the same file before they reach PHP, for an application that does not know the mode yet; with nginx:

```nginx
set $maintenance "";
if (-f /var/www/example.com/shared/maintenance.json) { set $maintenance "on"; }
if ($request_method !~ ^(GET|HEAD|OPTIONS)$) { set $maintenance "${maintenance}-write"; }
if ($maintenance = "on-write") { return 503; }
```
