<?php

declare(strict_types=1);

namespace Cosray\Webhooks;

use Cosray\Contract\Job;
use Cosray\Exception\JobFailed;
use Cosray\Exception\RuntimeException;
use Override;

/**
 * Posts one queued webhook event to one hook. A delivery that gets no
 * response or a server error runs again later; one the hook refuses with
 * a client error, other than a timeout or a rate limit, fails at once.
 *
 * @internal
 */
final class Delivery implements Job
{
	private const array RETRYABLE = [408, 429];

	public function __construct(
		private readonly Client $client,
	) {}

	#[Override]
	public function handle(array $payload): void
	{
		$url = $payload['url'] ?? null;
		$headers = $payload['headers'] ?? null;
		$body = $payload['body'] ?? null;

		if (!is_string($url) || !is_array($headers) || !is_string($body)) {
			throw new JobFailed('A queued webhook delivery lacks its url, headers or body.');
		}

		/** @var array<string, string> $headers */
		$status = $this->client->post($url, $headers, $body);

		if ($status !== null && $status >= 200 && $status < 300) {
			return;
		}

		$event = $headers['X-Cosray-Event'] ?? 'event';
		$message = "Webhook {$event} to {$url} failed with " . ($status ?? 'no response');

		if ($status !== null && $status >= 400 && $status < 500 && !in_array($status, self::RETRYABLE, true)) {
			throw new JobFailed($message);
		}

		throw new RuntimeException($message);
	}
}
