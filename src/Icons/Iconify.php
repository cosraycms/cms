<?php

declare(strict_types=1);

namespace Celemas\Cms\Icons;

use Celemas\Cms\Config;
use Celemas\Cms\Contract;
use Closure;

final class Iconify implements Contract\Icons
{
	/** @var Closure(string, int, string): ?string */
	private readonly Closure $fetch;

	public function __construct(
		private readonly Config $config,
		?callable $fetch = null,
	) {
		$this->fetch = $fetch === null
			? Closure::fromCallable([$this, 'request'])
			: Closure::fromCallable($fetch);
	}

	/** @param array<array-key, mixed> $args */
	public function icon(string $id, array $args = []): string
	{
		$parts = $this->split($id);

		if ($parts === null) {
			return '';
		}

		$svg = $this->loadSvg($id, $parts['prefix'], $parts['name'], $args);

		return $svg ?? '';
	}

	/**
	 * @return array{prefix: string, name: string}|null
	 */
	private function split(string $id): ?array
	{
		$id = trim($id);

		if (!preg_match(
			'/^(?<prefix>[a-z0-9]+(?:[-_][a-z0-9]+)*):(?<name>[a-z0-9]+(?:[-_][a-z0-9]+)*)$/i',
			$id,
			$matches,
		)) {
			return null;
		}

		$prefix = strtolower((string) $matches['prefix']);
		$name = strtolower((string) $matches['name']);

		if ($prefix === '' || $name === '') {
			return null;
		}

		return ['prefix' => $prefix, 'name' => $name];
	}

	/** @param array<array-key, mixed> $args */
	private function loadSvg(string $id, string $prefix, string $name, array $args): ?string
	{
		$file = $this->cacheFile($id, $args);

		if ($file !== null && is_file($file)) {
			$cached = file_get_contents($file);

			if (is_string($cached) && $this->isSvg($cached)) {
				return $cached;
			}
		}

		$url = $this->iconUrl($prefix, $name, $args);
		$iconify = $this->config->icons->iconify;
		$svg = ($this->fetch)($url, $iconify->timeout, $iconify->userAgent);

		if (!is_string($svg) || !$this->isSvg($svg)) {
			return null;
		}

		if ($file !== null) {
			$this->store($file, $svg);
		}

		return $svg;
	}

	/** @param array<array-key, mixed> $args */
	private function iconUrl(string $prefix, string $name, array $args): string
	{
		$base = rtrim($this->config->icons->iconify->baseUrl, '/');
		$url = sprintf('%s/%s/%s.svg', $base, rawurlencode($prefix), rawurlencode($name));
		$query = $this->query($args);

		return $query === '' ? $url : $url . '?' . $query;
	}

	/** @param array<array-key, mixed> $args */
	private function query(array $args): string
	{
		$args = $this->queryArgs($args);

		if ($args === []) {
			return '';
		}

		return http_build_query($args, '', '&', PHP_QUERY_RFC3986);
	}

	/**
	 * @param array<array-key, mixed> $args
	 * @return array<array-key, mixed>
	 */
	private function queryArgs(array $args): array
	{
		$query = [];

		foreach ($args as $key => $value) {
			$value = $this->queryValue($value);

			if ($value !== null) {
				$query[$key] = $value;
			}
		}

		if (!array_is_list($query)) {
			ksort($query, SORT_STRING);
		}

		return $query;
	}

	/** @return scalar|array<array-key, mixed>|null */
	private function queryValue(mixed $value): mixed
	{
		if (is_scalar($value)) {
			return $value;
		}

		if (is_array($value)) {
			return $this->queryArgs($value);
		}

		return null;
	}

	private function request(string $url, int $timeout, string $userAgent): ?string
	{
		$handle = curl_init($url);

		if ($handle === false) {
			return null;
		}

		curl_setopt_array($handle, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CONNECTTIMEOUT => $timeout,
			CURLOPT_TIMEOUT => $timeout,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_USERAGENT => $userAgent,
			CURLOPT_HTTPHEADER => ['Accept: image/svg+xml,text/plain;q=0.9,*/*;q=0.1'],
		]);
		$body = curl_exec($handle);
		$status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
		$error = curl_errno($handle);

		if (!is_string($body) || $error !== 0 || $status < 200 || $status >= 300) {
			return null;
		}

		return $body;
	}

	/** @param array<array-key, mixed> $args */
	private function cacheFile(string $id, array $args): ?string
	{
		$dir = $this->cacheDir();

		if ($dir === null) {
			return null;
		}

		$query = $this->query($args);
		$cacheId = $query === '' ? $id : $id . '?' . $query;

		return $dir . DIRECTORY_SEPARATOR . hash('xxh3', $cacheId) . '.svg';
	}

	private function cacheDir(): ?string
	{
		$publicDir = realpath($this->config->path->public);

		if ($publicDir === false) {
			return null;
		}

		$cacheDir = $this->config->path->cache;

		if ($cacheDir === '' || str_contains(str_replace('\\', '/', $cacheDir), '..')) {
			return null;
		}

		$target =
			rtrim($publicDir, '\\/')
			. DIRECTORY_SEPARATOR
			. ltrim($cacheDir, '\\/')
			. DIRECTORY_SEPARATOR
			. 'icons';

		if (!is_dir($target) && !mkdir($target, 0o755, true) && !is_dir($target)) {
			return null;
		}

		$resolved = realpath($target);

		if ($resolved === false || strncmp($resolved, $publicDir, strlen($publicDir)) !== 0) {
			return null;
		}

		return $resolved;
	}

	private function store(string $file, string $svg): void
	{
		$temp = $file . '.tmp.' . bin2hex(random_bytes(6));

		if (file_put_contents($temp, $svg, LOCK_EX) === false) {
			unlink($temp);
			return;
		}

		if (!rename($temp, $file)) {
			if (is_file($temp)) {
				unlink($temp);
			}
		}
	}

	private function isSvg(string $svg): bool
	{
		return str_starts_with(ltrim($svg), '<svg');
	}
}
