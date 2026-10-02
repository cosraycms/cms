<?php

declare(strict_types=1);

namespace Cosray;

use Celema\Container\Container;
use Celema\Container\Resettable;
use Cosray\Icons\Provider;

/**
 * Looks up icons in the registered providers and caches the markup. The
 * service lives as long as the app, in a worker across requests: found
 * icons stay cached up to a limit, missing ones only until the request
 * ends, so a provider that failed for a moment is asked again.
 */
final class Icons implements Provider, Resettable
{
	private const int MAX_CACHED = 500;

	/** @var array<string, string> */
	private array $found = [];

	/** @var array<string, string> */
	private array $missing = [];

	public function __construct(
		private readonly Container $container,
		private readonly Config $config,
	) {}

	/** @param array<array-key, mixed> $args */
	public function icon(string $id, array $args = []): string
	{
		$id = trim($id);

		if ($id === '') {
			return $this->failed('empty icon id');
		}

		$key = $this->key($id, $args);
		$cached = $this->found[$key] ?? $this->missing[$key] ?? null;

		if ($cached !== null) {
			return $cached;
		}

		foreach ($this->providers() as $provider) {
			$svg = $provider->icon($id, $args);

			if ($svg === '') {
				continue;
			}

			if (count($this->found) >= self::MAX_CACHED) {
				// Drops the icon cached first; the cache only saves provider lookups.
				unset($this->found[array_key_first($this->found)]);
			}

			return $this->found[$key] = $svg;
		}

		return $this->missing[$key] = $this->failed('icon not found: ' . $id);
	}

	/** Forgets the icons that were not found, at the end of every request. */
	public function reset(): void
	{
		$this->missing = [];
	}

	/** @return iterable<Provider> */
	private function providers(): iterable
	{
		$tag = $this->container->tag(Provider::class);

		foreach ($tag->entries() as $id) {
			$provider = $tag->get($id);

			if ($provider instanceof Provider) {
				yield $provider;
			}
		}
	}

	/** @param array<array-key, mixed> $args */
	private function key(string $id, array $args): string
	{
		return hash('xxh3', $id . "\x1f" . serialize($args));
	}

	private function failed(string $message): string
	{
		if (!$this->config->debug()) {
			return '';
		}

		return sprintf('<!-- %s -->', escape($message));
	}
}
