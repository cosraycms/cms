<?php

declare(strict_types=1);

namespace Cosray\Config;

use Cosray\Exception\RuntimeException;
use Cosray\Util\IpRange;
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

	/**
	 * The site's name as people read it, in mails for example, unlike
	 * `app.name`, which names the session cookies.
	 */
	public string $title {
		get {
			$title = $this->config->get('app.title');

			if (is_string($title) && trim($title) !== '') {
				return trim($title);
			}

			return parse_url((string) $this->url, PHP_URL_HOST) ?: $this->name;
		}
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

	/**
	 * Addresses and CIDR ranges of reverse proxies whose `X-Forwarded-For`
	 * names the client.
	 *
	 * @var list<non-empty-string>
	 */
	public array $trustedProxies {
		get => self::proxies($this->config->get('app.trusted_proxies'));
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

	/** @return list<non-empty-string> */
	private static function proxies(mixed $proxies): array
	{
		$proxies = is_string($proxies) ? explode(',', $proxies) : $proxies;

		if (!is_array($proxies)) {
			throw new RuntimeException('`app.trusted_proxies` takes a list of IP addresses and CIDR ranges.');
		}

		$valid = [];

		foreach ($proxies as $proxy) {
			$proxy = is_string($proxy) ? trim($proxy) : '';

			if ($proxy === '') {
				continue;
			}

			if (!IpRange::valid($proxy)) {
				throw new RuntimeException("`app.trusted_proxies` has {$proxy}, which is no IP address or CIDR range.");
			}

			$valid[] = $proxy;
		}

		return $valid;
	}
}
