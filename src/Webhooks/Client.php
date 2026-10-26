<?php

declare(strict_types=1);

namespace Cosray\Webhooks;

/**
 * Posts one webhook request with short timeouts.
 *
 * @internal
 */
class Client
{
	/**
	 * @param array<string, string> $headers
	 * @return ?int the response status, null without a response
	 */
	public function post(string $url, array $headers, string $body): ?int
	{
		$handle = curl_init($url);
		curl_setopt_array($handle, [
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => $body,
			CURLOPT_HTTPHEADER => array_map(
				static fn(string $name, string $value): string => "{$name}: {$value}",
				array_keys($headers),
				$headers,
			),
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CONNECTTIMEOUT => 2,
			CURLOPT_TIMEOUT => 5,
			CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
		]);
		curl_exec($handle);
		$status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);

		return $status > 0 ? $status : null;
	}
}
