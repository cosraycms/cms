<?php

declare(strict_types=1);

namespace Cosray;

use Celema\Quma\Database;
use Closure;
use Cosray\Exception\RuntimeException;
use Cosray\Webhooks\Delivery;

/**
 * Tells other systems that content changed, such as a static site build or a
 * cache in front of the API. Each event queues one delivery job per hook,
 * which retries a failed POST. The jobs are pushed inside the write's
 * transaction, so they exist only if the write commits, whether it happens
 * in a request, a job or a command.
 *
 *     'webhooks' => [
 *         ['url' => 'https://build.example.com/hook', 'secret' => $secret, 'events' => ['node.*']],
 *     ],
 *
 * Events are `node.created`, `node.updated` (a live write, publishing and
 * unpublishing included), `node.draft` (a working copy saved or dropped) and
 * `node.deleted`. The body holds `id`, `event`, `at` and the node's `uid` and
 * `type`; with a secret, `X-Cosray-Signature` carries `sha256=` and the
 * HMAC-SHA256 of the body.
 */
final class Webhooks
{
	public const string USER_AGENT = 'cosray-webhooks';

	/** @var list<array{url: string, secret: ?string, events: list<string>}> */
	private readonly array $hooks;

	/**
	 * @param list<array{url: string, secret?: ?string, events?: list<string>}> $hooks
	 * @param Closure(array{url: string, headers: array<string, string>, body: string}): void $queue
	 *        queues the delivery of one request
	 */
	public function __construct(
		array $hooks,
		private readonly Closure $queue,
	) {
		$this->hooks = array_map(self::hook(...), $hooks);
	}

	public static function fromConfig(Config $config, Database $db): self
	{
		$hooks = $config->get('webhooks', []);

		if (!is_array($hooks) || !array_is_list($hooks)) {
			throw new RuntimeException('`webhooks` takes a list of hooks.');
		}

		$queue = new Queue($db);

		/** @var list<array{url: string, secret?: ?string, events?: list<string>}> $hooks */
		return new self(
			$hooks,
			static function (array $request) use ($queue): void {
				$queue->push(Delivery::class, $request, queue: 'webhooks');
			},
		);
	}

	/**
	 * Queues the deliveries of an event on the write's connection.
	 *
	 * @param array<string, mixed> $data
	 */
	public function record(string $event, array $data): void
	{
		$id = bin2hex(random_bytes(16));
		$body = null;

		foreach ($this->hooks as $hook) {
			if (!self::subscribes($hook['events'], $event)) {
				continue;
			}

			$body ??= json_encode(
				['id' => $id, 'event' => $event, 'at' => gmdate('Y-m-d\TH:i:s\Z'), ...$data],
				JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
			);
			$headers = [
				'Content-Type' => 'application/json',
				'User-Agent' => self::USER_AGENT,
				'X-Cosray-Event' => $event,
				'X-Cosray-Delivery' => $id,
			];

			if ($hook['secret'] !== null) {
				$headers['X-Cosray-Signature'] = 'sha256=' . hash_hmac('sha256', $body, $hook['secret']);
			}

			($this->queue)(['url' => $hook['url'], 'headers' => $headers, 'body' => $body]);
		}
	}

	/** @param list<string> $patterns */
	private static function subscribes(array $patterns, string $event): bool
	{
		foreach ($patterns as $pattern) {
			if (
				$pattern === '*'
				|| $pattern === $event
				|| str_ends_with($pattern, '.*')
				&& str_starts_with($event, substr($pattern, 0, -1))
			) {
				return true;
			}
		}

		return false;
	}

	/** @return array{url: string, secret: ?string, events: list<string>} */
	private static function hook(mixed $hook): array
	{
		$url = is_array($hook) ? $hook['url'] ?? null : null;

		if (!is_string($url) || preg_match('#^https?://#i', $url) !== 1) {
			throw new RuntimeException('A webhook needs an http or https `url`.');
		}

		$secret = $hook['secret'] ?? null;
		$events = $hook['events'] ?? ['*'];

		if ($secret !== null && !is_string($secret) || !is_array($events) || !array_is_list($events)) {
			throw new RuntimeException("The webhook for {$url} takes a string `secret` and a list of `events`.");
		}

		return ['url' => $url, 'secret' => $secret, 'events' => array_values(array_filter($events, is_string(...)))];
	}
}
