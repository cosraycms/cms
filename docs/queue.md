# Job queue

Cosray keeps a job queue in the site's database for work that should not hold up a response or must survive a failure, like calling another service. By default, requests run due jobs once their response is out, so the queue works on any host; where cron is available, `queue:work` can take over. See [Queue](../src/Queue.php), [Contract\Job](../src/Contract/Job.php) and [Queue\Runner](../src/Queue/Runner.php).

## Jobs

A job is a class implementing `Cosray\Contract\Job`. Cosray builds it through the container for every run, so its constructor receives services:

```php
use Cosray\Config;
use Cosray\Contract\Job;
use Cosray\Exception\JobFailed;
use RuntimeException;

final class NotifyBuildServer implements Job
{
    public function __construct(
        private readonly Config $config,
    ) {}

    public function handle(array $payload): void
    {
        $url = $this->config->get('build.hook', null) ?? throw new JobFailed('No build hook configured');

        if (@file_get_contents($url . '?reason=' . rawurlencode($payload['reason'])) === false) {
            throw new RuntimeException('The build server did not answer');
        }
    }
}
```

Push it from any code that receives `Cosray\Queue`, like a node or a controller:

```php
$this->queue->push(NotifyBuildServer::class, ['reason' => 'menu changed']);
$this->queue->push(NotifyBuildServer::class, ['reason' => 'nightly'], delay: 3600);
$this->queue->push(ImportFeed::class, ['feed' => 'events'], queue: 'imports');
```

- The payload reaches the job after a JSON round trip. Pass ids and plain values, not objects.
- A job runs without a request: there is no session, no user and no request locale. Translations use the default locale.
- A job runs at least once. If its runner dies after the work but before recording it, the job runs again, so make repeating it harmless where you can.
- A claimed job holds a lease of five minutes. Work that may take longer can be started a second time by another runner; split it into smaller jobs.

### Failures

An exception fails the attempt. The job runs again after 1 minute, then after 5 minutes, 30 minutes, 2 hours and 12 hours. After its sixth attempt it fails for good. Throw `Cosray\Exception\JobFailed` when another attempt cannot help, like for an address that does not exist; the job then fails at once. Each failed attempt is logged as a `warning`, a job that failed for good as an `error`.

A job that leaves a transaction open counts as a failed attempt; the runner rolls the transaction back.

### Transactions

A push inside a transaction belongs to it: the job is queued only if the transaction commits. A write and the work it causes then happen together or not at all. Cosray's own writes join an open transaction:

```php
$this->db->begin();

try {
    $this->store->save($submission, $data, $locales, Actor::system());
    $this->queue->push(ConfirmSubmission::class, ['email' => $data['email']]);
    $this->db->commit();
} catch (Throwable $e) {
    $this->db->rollback();

    throw $e;
}
```

## Running jobs

Runners claim one job at a time, so any number can run at once, after responses and from cron alike.

### After responses

After each response, the request runs due jobs: at most 10, and none started after 10 seconds. PHP-FPM and FrankenPHP have sent the response by then; the development server and setups without an early finish, like `mod_php` or CGI, keep the visitor waiting. The request's PHP worker stays busy meanwhile, and PHP's time limit still applies.

Retries and delayed jobs run with the first request after they are due, so a site without visitors runs them late. Where cron runs `queue:work`, turn this off with `QUEUE_AFTER_RESPONSE=false` or the `queue.after_response` setting.

### Cron

`queue:work` runs due jobs until none is left and prints a summary only if it ran any, so cron mails nothing on quiet runs:

```text
* * * * * cd /srv/site && php run queue:work --max-time=50
```

- `--max-time=<seconds>`: start no job after this many seconds. Keep it below the cron interval.
- `--max-jobs=<count>`: stop after this many jobs.
- `--queue=<name>`: run only the jobs pushed under this name.

## Failed jobs

A job that failed for good stays in the database with its last error:

- `php run queue:failed` lists them.
- `php run queue:retry <id>` or `php run queue:retry all` queues them again with fresh attempts.
- `php run queue:prune --days=30` deletes those that failed more than the given number of days ago.
