<?php

declare(strict_types=1);

namespace Cosray;

use Celema\Container\Container;
use Cosray\Icons\Provider;

final class Icons implements Provider
{
	/** @var array<string, string> */
	private array $cache = [];

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

		if (array_key_exists($key, $this->cache)) {
			return $this->cache[$key];
		}

		foreach ($this->providers() as $provider) {
			$svg = $provider->icon($id, $args);

			if ($svg === '') {
				continue;
			}

			return $this->cache[$key] = $svg;
		}

		return $this->cache[$key] = $this->failed('icon not found: ' . $id);
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
