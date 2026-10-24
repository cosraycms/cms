<?php

declare(strict_types=1);

namespace Cosray;

use Closure;
use Cosray\Exception\RuntimeException;
use Psr\Log\LoggerInterface as Logger;

/**
 * Tells other systems that content changed, such as a static site build or a
 * cache in front of the API. Events are recorded once their write is
 * committed and delivered when the request ends, one POST per event and
 * hook, in parallel and with short timeouts. A failed delivery is logged,
 * not retried.
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

	/** @var list<array{url: string, headers: array<string, string>, body: string}> */
	private array $pending = [];

	/**
	 * @param list<array{url: string, secret?: ?string, events?: list<string>}> $hooks
	 * @param ?Closure(list<array{url: string, headers: array<string, string>, body: string}>): list<?int> $send
	 *        delivers the requests and returns each one's status, null when it failed
	 */
	public function __construct(
		array $hooks,
		private readonly Logger $logger,
		private readonly ?Closure $send = null,
	) {
		$this->hooks = array_map(self::hook(...), $hooks);
	}

	public static function fromConfig(Config $config, Logger $logger): self
	{
		$hooks = $config->get('webhooks', []);

		if (!is_array($hooks) || !array_is_list($hooks)) {
			throw new RuntimeException('`webhooks` takes a list of hooks.');
		}

		/** @var list<array{url: string, secret?: ?string, events?: list<string>}> $hooks */
		return new self($hooks, $logger);
	}

	/** @param array<string, mixed> $data */
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

			$this->pending[] = ['url' => $hook['url'], 'headers' => $headers, 'body' => $body];
		}
	}

	public function deliver(): void
	{
		$requests = $this->pending;
		$this->pending = [];

		if ($requests === []) {
			return;
		}

		$statuses = ($this->send ?? self::curl(...))($requests);

		foreach ($requests as $index => $request) {
			$status = $statuses[$index] ?? null;

			if ($status === null || $status < 200 || $status >= 300) {
				$this->logger->warning('Webhook {event} to {url} failed with {status}', [
					'event' => $request['headers']['X-Cosray-Event'],
					'url' => $request['url'],
					'status' => $status ?? 'no response',
				]);
			}
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

	/**
	 * @param list<array{url: string, headers: array<string, string>, body: string}> $requests
	 * @return list<?int>
	 */
	private static function curl(array $requests): array
	{
		$multi = curl_multi_init();
		$handles = [];

		foreach ($requests as $request) {
			$handle = curl_init($request['url']);
			curl_setopt_array($handle, [
				CURLOPT_POST => true,
				CURLOPT_POSTFIELDS => $request['body'],
				CURLOPT_HTTPHEADER => array_map(
					static fn(string $name, string $value): string => "{$name}: {$value}",
					array_keys($request['headers']),
					$request['headers'],
				),
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_CONNECTTIMEOUT => 2,
				CURLOPT_TIMEOUT => 5,
				CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
			]);
			curl_multi_add_handle($multi, $handle);
			$handles[] = $handle;
		}

		do {
			$status = curl_multi_exec($multi, $running);

			if ($running > 0) {
				curl_multi_select($multi, 1.0);
			}
		} while ($running > 0 && $status === CURLM_OK);

		$statuses = [];

		foreach ($handles as $handle) {
			$code = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
			$statuses[] = $code > 0 ? $code : null;
			curl_multi_remove_handle($multi, $handle);
		}

		curl_multi_close($multi);

		return $statuses;
	}
}
