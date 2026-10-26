<?php

declare(strict_types=1);

namespace Cosray\Config;

use Cosray\Exception\RuntimeException;
use DateTimeZone;

final class App
{
	private ?DateTimeZone $timezoneCache = null;

	public function __construct(
		private readonly \Cosray\Config $config,
	) {}

	public string $name {
		get => $this->config->get('app.name');
	}

	public bool $debug {
		get => $this->config->get('app.debug');
	}

	public string $env {
		get => $this->config->get('app.env');
	}

	public string $urlPrefix {
		get => $this->config->get('app.url_prefix');
	}

	/**
	 * The absolute URL the application is served under, like
	 * `https://example.org`, for links that leave the request behind, like
	 * those in mails. Never taken from the request's host header.
	 */
	public ?string $url {
		get => self::url($this->config->get('app.url'));
	}

	public DateTimeZone $timezone {
		get => $this->timezoneCache ??= new DateTimeZone($this->config->get('app.timezone'));
	}

	public ?string $secret {
		get => $this->config->get('app.secret');
	}

	private static function url(mixed $url): ?string
	{
		if ($url === null || $url === '') {
			return null;
		}

		if (!is_string($url) || preg_match('#^https?://[^/\s]+#i', $url) !== 1) {
			throw new RuntimeException('`app.url` takes an absolute http or https URL, like https://example.org.');
		}

		return rtrim($url, '/');
	}
}
