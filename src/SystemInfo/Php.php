<?php

declare(strict_types=1);

namespace Cosray\SystemInfo;

/**
 * The PHP runtime a system report reads: the running process's own,
 * unless values passed here stand in for some of it.
 */
final class Php
{
	private const string MASK = '••••';

	/**
	 * @param array<string, string> $ini directive => value
	 * @param array<string, ?string> $extensions name => version, null for a missing one
	 */
	public function __construct(
		private readonly array $ini = [],
		private readonly array $extensions = [],
		public readonly string $version = PHP_VERSION,
		public readonly string $sapi = PHP_SAPI,
	) {}

	public function ini(string $name): ?string
	{
		if (array_key_exists($name, $this->ini)) {
			return $this->ini[$name];
		}

		$value = ini_get($name);

		return $value === false ? null : $value;
	}

	/** The extension's version, `''` when it has none, null when it is not loaded. */
	public function extension(string $name): ?string
	{
		if (array_key_exists($name, $this->extensions)) {
			return $this->extensions[$name];
		}

		if (!extension_loaded($name)) {
			return null;
		}

		return self::version($name);
	}

	/** @return list<array{name: string, local: ?string, master: ?string}> */
	public function directives(): array
	{
		$directives = [];

		foreach (ini_get_all(null, true) ?: [] as $name => $values) {
			$directives[] = [
				'name' => (string) $name,
				'local' => self::masked((string) $name, self::scalar($values['local_value'] ?? null)),
				'master' => self::masked((string) $name, self::scalar($values['global_value'] ?? null)),
			];
		}

		return $directives;
	}

	/** @return list<array{name: string, version: string}> */
	public function extensions(): array
	{
		$extensions = [];

		foreach (get_loaded_extensions() as $name) {
			$extensions[] = ['name' => $name, 'version' => self::version($name)];
		}

		usort($extensions, static fn(array $left, array $right): int => strcasecmp($left['name'], $right['name']));

		return $extensions;
	}

	/** Hides passwords, as of `mysqli.default_pw` or a `session.save_path` like `tcp://host?auth=secret`. */
	public static function masked(string $name, ?string $value): ?string
	{
		if ($value === null || $value === '') {
			return $value;
		}

		if (preg_match('/(?:^|[._])(?:pw|pass|password|secret|token)$/i', $name) === 1) {
			return self::MASK;
		}

		return preg_replace(
			['#//[^/@\s]+@#', '/\b(auth|pass|password|secret|token)=[^&;\s]+/i'],
			['//' . self::MASK . '@', '$1=' . self::MASK],
			$value,
		) ?? self::MASK;
	}

	/** Some builds leave a placeholder such as `@PACKAGE_VERSION@` instead of the version. */
	private static function version(string $extension): string
	{
		$version = phpversion($extension) ?: '';

		return preg_match('/\A@\w+@\z/', $version) === 1 ? '' : $version;
	}

	private static function scalar(mixed $value): ?string
	{
		return is_scalar($value) ? (string) $value : null;
	}
}
