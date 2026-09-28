<?php

declare(strict_types=1);

namespace Cosray\Util;

use Celema\Core\Request;

/**
 * The script of celema/server's live reload. The server only sets its URL
 * while running with `--watch`, so pages can include it unconditionally.
 */
final class LiveReload
{
	/**
	 * The script URL, or null without a watching dev server. It gets the host
	 * the page was requested under, so the script also loads on other devices,
	 * in virtual machines, and under local domain names, as long as the dev
	 * server listens there.
	 */
	public static function url(?Request $request): ?string
	{
		$url = getenv('CELEMA_LIVE_RELOAD');

		if (!is_string($url) || $url === '') {
			return null;
		}

		$host = $request?->uri()->getHost() ?? '';

		return $host === '' ? $url : self::withHost($url, $host);
	}

	private static function withHost(string $url, string $host): string
	{
		$parts = parse_url($url);

		if ($parts === false) {
			return $url;
		}

		if (str_contains($host, ':') && !str_starts_with($host, '[')) {
			$host = "[{$host}]";
		}

		return (
			($parts['scheme'] ?? 'http')
				. "://{$host}"
				. (isset($parts['port']) ? ":{$parts['port']}" : '')
				. ($parts['path'] ?? '')
				. (isset($parts['query']) ? "?{$parts['query']}" : '')
		);
	}
}
