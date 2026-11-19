<?php

declare(strict_types=1);

namespace Cosray\SystemInfo;

use Cosray\Panel\AssetFacts;

/**
 * How the panel and the console state a system report's entries: their
 * labels, values, and what to do about a problem.
 *
 * @phpstan-import-type Entry from \Cosray\SystemInfo
 */
final class Text
{
	/** Entries whose values are byte counts; -1 stands for no limit. */
	private const array BYTES = ['upload.file', 'upload.video', 'php.memory_limit'];

	public function __construct(
		private readonly string $locale = 'en',
	) {}

	public function group(string $key): string
	{
		return match ($key) {
			'runtime' => __('system-info:group-runtime'),
			'extensions' => __('system-info:group-extensions'),
			'limits' => __('system-info:group-limits'),
			'database' => __('system-info:group-database'),
			'paths' => __('system-info:group-paths'),
			default => $key,
		};
	}

	public function label(string $key): string
	{
		return match ($key) {
			'cosray.version' => 'Cosray',
			'php.version' => 'PHP',
			'php.sapi' => __('system-info:sapi'),
			'app.env' => __('system-info:env'),
			'app.debug' => __('system-info:debug'),
			'app.timezone' => __('system-info:timezone'),
			'php.opcache' => 'OPcache',
			'php.file_uploads' => __('system-info:file-uploads'),
			'upload.file' => __('system-info:upload-file'),
			'upload.video' => __('system-info:upload-video'),
			'php.memory_limit' => __('system-info:memory-limit'),
			'php.max_execution_time' => __('system-info:time-limit'),
			'db.driver' => __('system-info:db-driver'),
			'db.server' => __('system-info:db-server'),
			'db.prefix' => __('system-info:db-prefix'),
			'path.cache' => __('system-info:path-cache'),
			'path.assets' => __('system-info:path-assets'),
			default => str_starts_with($key, 'ext.') ? substr($key, 4) : $key,
		};
	}

	/** @param Entry $entry */
	public function value(array $entry): string
	{
		$value = $entry['value'];

		return match (true) {
			in_array($entry['key'], self::BYTES, true) => $this->bytes((int) $value),
			$entry['key'] === 'php.max_execution_time' => (int) $value === 0
				? __('system-info:unlimited')
				: __('system-info:seconds', ['seconds' => (int) $value]),
			$entry['key'] === 'db.server' && $value === null => __('system-info:unreachable'),
			str_starts_with($entry['key'], 'ext.') => match ($value) {
				null => __('system-info:missing'),
				'' => __('system-info:loaded'),
				default => (string) $value,
			},
			$value === true => __('system-info:on'),
			$value === false => __('system-info:off'),
			$value === null, $value === '' => '–',
			default => (string) $value,
		};
	}

	/**
	 * What a problem means and how to solve it; null for an entry without one.
	 *
	 * @param Entry $entry
	 */
	public function hint(array $entry): ?string
	{
		if ($entry['status'] !== 'warn' && $entry['status'] !== 'fail') {
			return null;
		}

		$expected = $entry['expected'] ?? null;

		return match ($entry['key']) {
			'app.debug' => __('system-info:hint-debug'),
			'php.opcache' => __('system-info:hint-opcache'),
			'php.file_uploads' => __('system-info:hint-file-uploads'),
			'upload.file' => __('system-info:hint-upload-file', [
				'php' => $this->value($entry),
				'cosray' => $this->bytes((int) $expected),
			]),
			'upload.video' => __('system-info:hint-upload-video', [
				'php' => $this->value($entry),
				'cosray' => $this->bytes((int) $expected),
			]),
			'php.memory_limit' => __('system-info:hint-memory-limit'),
			'db.server' => $entry['value'] === null
				? __('system-info:hint-db-unreachable')
				: __('system-info:hint-db-version', ['version' => (string) $expected]),
			'path.cache', 'path.assets' => __('system-info:hint-path'),
			'ext.imagick' => __('system-info:hint-imagick'),
			default => str_starts_with($entry['key'], 'ext.')
				? __('system-info:hint-extension', ['name' => substr($entry['key'], 4)])
				: null,
		};
	}

	private function bytes(int $bytes): string
	{
		return $bytes < 0 ? __('system-info:unlimited') : AssetFacts::size($bytes, $this->locale);
	}
}
