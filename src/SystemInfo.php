<?php

declare(strict_types=1);

namespace Cosray;

use Celema\Quma\Connection;
use Celema\Quma\Database;
use Celema\Quma\Environment;
use Celema\Quma\Migrations\DriverPolicy;
use Celema\Quma\Migrations\Log;
use Celema\Quma\Migrations\Planner;
use Composer\InstalledVersions;
use Cosray\SystemInfo\Php;
use PDO;
use Throwable;

/**
 * What the runtime offers Cosray: versions, extensions, limits, the
 * database and the writable directories, each entry rated against what
 * Cosray needs. Values are raw (bytes, seconds, booleans); presentation
 * belongs to the caller, see SystemInfo\Text.
 *
 * @phpstan-type Status 'ok'|'warn'|'fail'|'info'
 * @phpstan-type Value bool|int|string|null
 * @phpstan-type Entry array{key: string, value: Value, status: Status, expected?: bool|int|string}
 * @phpstan-type Group array{key: string, status: Status, entries: list<Entry>}
 * @phpstan-type Report array{status: Status, groups: list<Group>}
 */
final class SystemInfo
{
	/**
	 * What Cosray and the Celema packages require, besides the PDO driver
	 * of the configured database.
	 */
	public const array EXTENSIONS = [
		'curl',
		'dom',
		'fileinfo',
		'gd',
		'intl',
		'mbstring',
		'pdo',
		'pgsql',
		'sodium',
		'xml',
	];

	/** With GD's roughly 5 bytes per pixel, enough to resize images of about 25 megapixels. */
	private const int MEMORY_MINIMUM = 128 * 1024 * 1024;

	private const int POSTGRESQL_MINIMUM = 17;

	/** Runners from cron or after responses take due jobs within minutes; this long means none runs. */
	private const int OVERDUE_MINUTES = 30;

	public function __construct(
		private readonly Config $config,
		private readonly Database $db,
		private readonly Connection $connection,
		private readonly Php $php = new Php(),
	) {}

	/** @return Report */
	public function report(): array
	{
		$groups = [
			self::group('runtime', $this->runtime()),
			self::group('limits', $this->limits()),
			self::group('extensions', $this->extensions()),
			self::group('database', $this->database()),
			self::group('queue', $this->queue()),
			self::group('paths', $this->paths()),
		];

		$groups = array_values(array_filter($groups, static fn(array $group): bool => $group['entries'] !== []));

		return ['status' => self::worst(array_column($groups, 'status')), 'groups' => $groups];
	}

	/**
	 * Every ini directive and loaded extension, for a look beyond the report.
	 *
	 * @return array{
	 *     directives: list<array{name: string, local: ?string, master: ?string}>,
	 *     extensions: list<array{name: string, version: string}>,
	 * }
	 */
	public function php(): array
	{
		return ['directives' => $this->php->directives(), 'extensions' => $this->php->extensions()];
	}

	/** @return list<Entry> */
	private function runtime(): array
	{
		$production = in_array(strtolower($this->config->env()), ['production', 'prod'], true);
		$debug = $this->config->debug();
		$opcache =
			$this->php->extension('Zend OPcache') !== null
			&& self::on($this->php->ini($this->web() ? 'opcache.enable' : 'opcache.enable_cli'));

		return [
			self::entry('cosray.version', self::cosrayVersion(), 'info'),
			self::entry('php.version', $this->php->version, 'info'),
			self::entry('php.sapi', $this->php->sapi, 'info'),
			self::entry('app.env', $this->config->env(), 'info'),
			self::entry('app.debug', $debug, $debug && $production ? 'warn' : 'info', $production ? false : null),
			self::entry('app.timezone', $this->config->app->timezone->getName(), 'info'),
			self::entry('php.opcache', $opcache, match (true) {
				$opcache => 'ok',
				$production && $this->web() => 'warn',
				default => 'info',
			}),
		];
	}

	/** @return list<Entry> */
	private function extensions(): array
	{
		$required = self::EXTENSIONS;
		$driver = $this->config->db->driver();

		if ($driver !== '') {
			$required[] = "pdo_{$driver}";
			sort($required);
		}

		$entries = [];

		foreach ($required as $name) {
			$version = $this->php->extension($name);
			$entries[] = self::entry("ext.{$name}", $version, $version === null ? 'fail' : 'ok');
		}

		$imagick = $this->php->extension('imagick');
		$needsImagick = $this->config->media->backend === 'imagick';
		$entries[] = self::entry('ext.imagick', $imagick, match (true) {
			$imagick !== null => 'ok',
			$needsImagick => 'fail',
			default => 'info',
		});

		foreach (['exif', 'pcntl'] as $name) {
			$version = $this->php->extension($name);
			$entries[] = self::entry("ext.{$name}", $version, $version === null ? 'info' : 'ok');
		}

		return $entries;
	}

	/** @return list<Entry> */
	private function limits(): array
	{
		$uploads = self::on($this->php->ini('file_uploads'));
		$file = self::bytes($this->php->ini('upload_max_filesize'));
		$post = self::bytes($this->php->ini('post_max_size'));
		// A post_max_size of 0 lifts that limit.
		$upload = $post > 0 ? min($file, $post) : $file;
		$fileLimit = $this->config->upload->maxSizeFor('file');
		$videoLimit = $this->config->upload->maxSizeFor('video');
		$memory = self::bytes($this->php->ini('memory_limit'));

		return [
			self::entry('php.file_uploads', $uploads, $this->rate($uploads, 'fail'), true),
			self::entry('upload.file', $upload, $this->rate($upload >= $fileLimit, 'fail'), $fileLimit),
			self::entry('upload.video', $upload, $this->rate($upload >= $videoLimit, 'warn'), $videoLimit),
			self::entry(
				'php.memory_limit',
				$memory,
				$memory < 0 || $memory >= self::MEMORY_MINIMUM ? 'ok' : 'warn',
				self::MEMORY_MINIMUM,
			),
			self::entry('php.max_execution_time', (int) $this->php->ini('max_execution_time'), 'info'),
		];
	}

	/** @return list<Entry> */
	private function database(): array
	{
		$driver = $this->config->db->driver();
		$entries = [self::entry('db.driver', $driver, 'info')];

		try {
			$version = (string) $this->db->getConn()->getAttribute(PDO::ATTR_SERVER_VERSION);
		} catch (Throwable) {
			$version = null;
		}

		if ($version === null) {
			$entries[] = self::entry('db.server', null, 'fail');
		} elseif ($driver === 'pgsql') {
			$entries[] = self::entry(
				'db.server',
				$version,
				(int) $version >= self::POSTGRESQL_MINIMUM ? 'ok' : 'fail',
				(string) self::POSTGRESQL_MINIMUM,
			);
		} else {
			$entries[] = self::entry('db.server', $version, 'info');
		}

		$entries[] = self::entry('db.prefix', $this->config->db->prefix(), 'info');

		if ($version !== null) {
			$pending = $this->pendingMigrations();
			$entries[] = self::entry('db.migrations', $pending, $pending === 0 ? 'ok' : 'fail', 0);
		}

		return $entries;
	}

	/**
	 * The migrations `db:migrations --apply` would run: those of the default
	 * and plugin namespaces not recorded yet. Null when the database has no
	 * record of migrations, as before the install.
	 */
	private function pendingMigrations(): ?int
	{
		try {
			$env = new Environment($this->connection);

			if (!$env->checkIfMigrationsTableExists($this->db)) {
				return null;
			}

			$planner = new Planner(new DriverPolicy($env->driver));
			$applied = new Log($env, $planner)->applied($this->db);
			$namespaces = $env->getMigrations() ?: [];
			$pending = 0;

			foreach ($this->connection->config->migrationOrder as $namespace) {
				$pending += count($planner->pendingMigrations($namespace, $namespaces[$namespace] ?? [], $applied));
			}

			return $pending;
		} catch (Throwable) {
			return null;
		}
	}

	/** @return list<Entry> */
	private function queue(): array
	{
		// Once the database proved unreachable, its entry already says so.
		if (!$this->db->connected()) {
			return [];
		}

		$queue = new Queue($this->db);

		try {
			$overdue = $queue->countOverdue(self::OVERDUE_MINUTES);
			$failed = $queue->countFailed();
		} catch (Throwable) {
			return [self::entry('queue.overdue', null, 'warn'), self::entry('queue.failed', null, 'warn')];
		}

		return [
			self::entry('queue.overdue', $overdue, $overdue === 0 ? 'ok' : 'warn', self::OVERDUE_MINUTES),
			self::entry('queue.failed', $failed, $failed === 0 ? 'ok' : 'warn', 0),
		];
	}

	/** @return list<Entry> */
	private function paths(): array
	{
		$public = rtrim($this->config->path->public, '\\/');
		$entries = [];

		foreach ([
			'path.cache' => $this->config->path->cache,
			'path.assets' => $this->config->path->assets,
		] as $key => $dir) {
			$path = $public . '/' . trim($dir, '/');
			$entries[] = self::entry($key, $path, self::writable($path) ? 'ok' : 'fail');
		}

		return $entries;
	}

	/**
	 * Only the web server's PHP receives uploads.
	 *
	 * @param 'fail'|'warn' $short
	 * @return Status
	 */
	private function rate(bool $enough, string $short): string
	{
		return match (true) {
			!$this->web() => 'info',
			$enough => 'ok',
			default => $short,
		};
	}

	private function web(): bool
	{
		return $this->php->sapi !== 'cli' && $this->php->sapi !== 'phpdbg';
	}

	/**
	 * @param list<Entry> $entries
	 * @return Group
	 */
	private static function group(string $key, array $entries): array
	{
		return ['key' => $key, 'status' => self::worst(array_column($entries, 'status')), 'entries' => $entries];
	}

	/**
	 * @param Value $value
	 * @param Status $status
	 * @return Entry
	 */
	private static function entry(
		string $key,
		bool|int|string|null $value,
		string $status,
		bool|int|string|null $expected = null,
	): array {
		$entry = ['key' => $key, 'value' => $value, 'status' => $status];

		if ($expected !== null) {
			$entry['expected'] = $expected;
		}

		return $entry;
	}

	/**
	 * @param list<Status> $statuses
	 * @return Status
	 */
	private static function worst(array $statuses): string
	{
		return match (true) {
			in_array('fail', $statuses, true) => 'fail',
			in_array('warn', $statuses, true) => 'warn',
			in_array('ok', $statuses, true) => 'ok',
			default => 'info',
		};
	}

	private static function on(?string $value): bool
	{
		return filter_var($value, FILTER_VALIDATE_BOOLEAN);
	}

	/** An ini quantity in bytes; -1 stands for no limit. */
	private static function bytes(?string $value): int
	{
		if ($value === null || $value === '') {
			return 0;
		}

		return ini_parse_quantity($value);
	}

	/** A directory PHP can write to, or one it can create. */
	private static function writable(string $path): bool
	{
		while (!file_exists($path)) {
			$parent = dirname($path);

			if ($parent === $path) {
				return false;
			}

			$path = $parent;
		}

		return is_dir($path) && is_writable($path);
	}

	private static function cosrayVersion(): ?string
	{
		if (!InstalledVersions::isInstalled('cosray/cms')) {
			return null;
		}

		$version = (string) InstalledVersions::getPrettyVersion('cosray/cms');
		$reference = (string) InstalledVersions::getReference('cosray/cms');

		if ($reference !== '' && (str_starts_with($version, 'dev-') || str_ends_with($version, '-dev'))) {
			return $version . '@' . substr($reference, 0, 8);
		}

		return $version;
	}
}
