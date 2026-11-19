<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Celema\Console\Buffer;
use Celema\Console\Io;
use Celema\Quma\Connection;
use Celema\Quma\Database;
use Celema\Quma\Delimiters;
use Celema\Router\Router;
use Celema\Verba\Translator;
use Celema\Verba\Verba;
use Cosray\App;
use Cosray\Config;
use Cosray\Console\Commands;
use Cosray\Locales;
use Cosray\SystemInfo;
use Cosray\SystemInfo\Php;
use Cosray\SystemInfo\Text;
use Cosray\Tests\TestCase;

/** @phpstan-import-type Entry from SystemInfo */
final class SystemInfoTest extends TestCase
{
	private const array FINE = [
		'file_uploads' => '1',
		'upload_max_filesize' => '512M',
		'post_max_size' => '512M',
		'memory_limit' => '256M',
		'max_execution_time' => '30',
		'opcache.enable' => '1',
	];

	protected function setUp(): void
	{
		parent::setUp();

		Verba::activate(new Translator('en', ['cosray' => self::root() . '/lang']));
	}

	public function testUploadsBelowTheConfiguredLimitAreAProblemWithAFix(): void
	{
		$info = $this->info(self::web(['upload_max_filesize' => '2M', 'post_max_size' => '8M']));
		$report = $info->report();
		$upload = self::entry($report, 'upload.file');

		$this->assertSame('fail', $report['status']);
		$this->assertSame('fail', $upload['status']);
		$this->assertSame(2 * 1024 * 1024, $upload['value']);
		$this->assertSame(10 * 1024 * 1024, $upload['expected'] ?? null);
		$this->assertSame(
			'Uploads over 2.0 MB fail: PHP allows 2.0 MB, Cosray accepts 10.0 MB. '
				. 'Raise upload_max_filesize and post_max_size.',
			new Text()->hint($upload),
		);
	}

	public function testThePlainTextReportMarksProblemsAndEndsWithTheirFixes(): void
	{
		$text = new Text()->plain($this->info(self::web(['upload_max_filesize' => '2M']))->report());

		$this->assertStringStartsWith("System info\n\nRuntime\n", $text);
		$this->assertStringContainsString("\n  Largest upload: 2.0 MB [Problem]\n", $text);
		$this->assertStringEndsWith(
			"\n- Largest video upload: Videos over 2.0 MB fail: Cosray accepts videos up to 256.0 MB. "
				. "Raise upload_max_filesize and post_max_size.\n",
			$text,
		);
	}

	public function testTheSmallerOfTheTwoUploadDirectivesLimitsUploads(): void
	{
		$post = self::entry(
			$this->info(self::web(['upload_max_filesize' => '64M', 'post_max_size' => '4M']))->report(),
			'upload.file',
		);
		$unlimitedPost = self::entry(
			$this->info(self::web(['upload_max_filesize' => '64M', 'post_max_size' => '0']))->report(),
			'upload.file',
		);

		$this->assertSame(4 * 1024 * 1024, $post['value']);
		$this->assertSame(64 * 1024 * 1024, $unlimitedPost['value']);
		$this->assertSame('ok', $unlimitedPost['status']);
	}

	public function testAVideoLimitOutOfReachIsOnlyAWarning(): void
	{
		$report = $this->info(self::web(['upload_max_filesize' => '64M', 'post_max_size' => '64M']))->report();

		$this->assertSame('warn', self::entry($report, 'upload.video')['status']);
		$this->assertSame('ok', self::entry($report, 'upload.file')['status']);
		$this->assertSame('warn', $report['status']);
	}

	public function testTheConsoleDoesNotRateTheUploadLimitsItNeverUses(): void
	{
		$cli = new Php([...self::FINE, 'upload_max_filesize' => '2M', 'opcache.enable_cli' => '0'], sapi: 'cli');
		$report = $this->info($cli, ['app.env' => 'production'])->report();

		$this->assertSame('info', self::entry($report, 'upload.file')['status']);
		$this->assertSame('info', self::entry($report, 'php.opcache')['status']);
		$this->assertNull(new Text()->hint(self::entry($report, 'upload.file')));
	}

	public function testAMissingRequiredExtensionIsAProblem(): void
	{
		$report = $this->info(self::web([], ['intl' => null]))->report();
		$intl = self::entry($report, 'ext.intl');

		$this->assertSame('fail', $intl['status']);
		$this->assertSame('missing', new Text()->value($intl));
		$this->assertSame('The PHP extension intl is missing.', new Text()->hint($intl));
		$this->assertSame('ok', self::entry($report, 'ext.pdo_sqlite')['status']);
	}

	public function testImagickIsRequiredOnlyAsTheMediaBackend(): void
	{
		$php = self::web([], ['imagick' => null]);

		$this->assertSame('info', self::entry($this->info($php)->report(), 'ext.imagick')['status']);
		$this->assertSame(
			'fail',
			self::entry($this->info($php, ['media.backend' => 'imagick'])->report(), 'ext.imagick')['status'],
		);
	}

	public function testDebugModeAndAMissingOpcacheWarnOnlyInProduction(): void
	{
		$php = self::web(['opcache.enable' => '0', 'opcache.enable_cli' => '0']);
		$development = $this->info($php, ['app.env' => 'development', 'app.debug' => true])->report();
		$production = $this->info($php, ['app.env' => 'production', 'app.debug' => true])->report();

		$this->assertSame('info', self::entry($development, 'app.debug')['status']);
		$this->assertSame('info', self::entry($development, 'php.opcache')['status']);
		$this->assertSame('warn', self::entry($production, 'app.debug')['status']);
		$this->assertSame('warn', self::entry($production, 'php.opcache')['status']);
	}

	public function testAnUnreachableDatabaseIsAProblem(): void
	{
		$config = $this->config(['db.dsn' => 'pgsql:host=not-a-database.invalid;dbname=none;connect_timeout=1']);
		$connection = self::connection($config);
		$info = new SystemInfo($config, new Database($connection), $connection, self::web([]));
		$server = self::entry($info->report(), 'db.server');

		$this->assertSame('fail', $server['status']);
		$this->assertSame('unreachable', new Text()->value($server));
		$this->assertSame('Cosray cannot connect to the database.', new Text()->hint($server));
	}

	public function testPendingMigrationsAreAProblem(): void
	{
		$dir = sys_get_temp_dir() . '/cosray-system-info-migrations-' . bin2hex(random_bytes(4));
		mkdir($dir);
		file_put_contents($dir . '/000001-first.sql', 'SELECT 1;');
		file_put_contents($dir . '/000002-second.sql', 'SELECT 1;');
		$config = $this->config(['db.dsn' => 'sqlite::memory:']);
		$connection = self::connection($config)->migrations(['default' => [$dir]]);
		$db = new Database($connection);
		$info = new SystemInfo($config, $db, $connection, self::web([]));

		try {
			$missing = self::entry($info->report(), 'db.migrations');
			$db->execute('CREATE TABLE migrations (migration TEXT, applied TEXT)')->run();
			$db->execute("INSERT INTO migrations (migration) VALUES ('000001-first.sql')")->run();
			$pending = self::entry($info->report(), 'db.migrations');
			$db->execute("INSERT INTO migrations (migration) VALUES ('000002-second.sql')")->run();
			$applied = self::entry($info->report(), 'db.migrations');
		} finally {
			array_map(unlink(...), glob($dir . '/*') ?: []);
			rmdir($dir);
		}

		$this->assertSame('fail', $missing['status']);
		$this->assertSame('unknown', new Text()->value($missing));
		$this->assertSame('fail', $pending['status']);
		$this->assertSame('1 pending', new Text()->value($pending));
		$this->assertSame(
			'The database is behind the code. Run php run db:migrations --apply.',
			new Text()->hint($pending),
		);
		$this->assertSame('ok', $applied['status']);
		$this->assertSame('all applied', new Text()->value($applied));
	}

	public function testFailedJobsAreAWarning(): void
	{
		$config = $this->config(['db.dsn' => 'sqlite::memory:']);
		$connection = self::connection($config);
		$db = new Database($connection);
		$info = new SystemInfo($config, $db, $connection, self::web([]));
		$db->execute('CREATE TABLE cms_jobs (state TEXT)')->run();
		$db->execute("INSERT INTO cms_jobs (state) VALUES ('failed'), ('failed'), ('pending')")->run();
		$failed = self::entry($info->report(), 'queue.failed');
		$db->execute('DROP TABLE cms_jobs')->run();
		$unreadable = self::entry($info->report(), 'queue.failed');

		$this->assertSame('warn', $failed['status']);
		$this->assertSame(2, $failed['value']);
		$this->assertSame(
			'2 jobs failed for good. php run queue:failed lists them, php run queue:retry queues them again.',
			new Text()->hint($failed),
		);
		$this->assertSame('warn', $unreadable['status']);
		$this->assertSame('unknown', new Text()->value($unreadable));
	}

	public function testDirectoriesPhpCannotWriteToAreAProblem(): void
	{
		$public = sys_get_temp_dir() . '/cosray-system-info-' . bin2hex(random_bytes(4));
		mkdir($public . '/assets', 0o777, true);
		chmod($public, 0o555);

		try {
			$report = $this->info(self::web([]), ['path.public' => $public])->report();
		} finally {
			chmod($public, 0o755);
			rmdir($public . '/assets');
			rmdir($public);
		}

		$this->assertSame('ok', self::entry($report, 'path.assets')['status']);
		$this->assertSame('fail', self::entry($report, 'path.cache')['status']);
	}

	public function testAHealthyRuntimeHasNoProblems(): void
	{
		$report = $this->info(self::web([]))->report();

		$this->assertNotSame('fail', $report['status']);
		$this->assertSame(
			['runtime', 'limits', 'extensions', 'database', 'queue', 'paths'],
			array_column($report['groups'], 'key'),
		);
	}

	public function testDirectivesKeepTheirPasswordsHidden(): void
	{
		$this->assertSame('••••', Php::masked('mysqli.default_pw', 'secret'));
		$this->assertSame('tcp://redis:6379?auth=••••&timeout=2', Php::masked(
			'session.save_path',
			'tcp://redis:6379?auth=secret&timeout=2',
		));
		$this->assertSame('redis://••••@cache:6379', Php::masked(
			'session.save_path',
			'redis://user:secret@cache:6379',
		));
		$this->assertSame('256M', Php::masked('memory_limit', '256M'));
	}

	public function testTheRequiredExtensionsCoverTheManifest(): void
	{
		$manifest = json_decode((string) file_get_contents(self::root() . '/composer.json'), true);
		$required = array_map(
			static fn(string $package): string => substr($package, 4),
			array_values(array_filter(
				array_keys($manifest['require']),
				static fn(string $package): bool => str_starts_with($package, 'ext-'),
			)),
		);

		$this->assertSame([], array_values(array_diff($required, SystemInfo::EXTENSIONS)));
	}

	public function testTheCommandPrintsTheReportAndFailsOnAProblem(): void
	{
		$config = $this->config(['db.dsn' => 'sqlite::memory:', 'error.enabled' => false]);
		$app = new App($config, $this->factory(), new Router(), $this->container());
		$locales = new Locales();
		$locales->add('en', title: 'English');
		$app->load($locales);
		$buffer = new Buffer();
		$code = new Commands($app, new Io($buffer))->runner()->run(['run', 'system:info', '--json']);
		$report = json_decode($buffer->output(), true);

		$this->assertIsArray($report);
		$this->assertSame($report['status'] === 'fail' ? 1 : 0, $code);
		$this->assertSame('sqlite', self::entry($report, 'db.driver')['value']);
	}

	/**
	 * @param array<string, string> $ini
	 * @param array<string, ?string> $extensions
	 */
	private static function web(array $ini, array $extensions = []): Php
	{
		return new Php([...self::FINE, ...$ini], $extensions, sapi: 'fpm-fcgi');
	}

	/** @param array<string, mixed> $settings */
	private function info(Php $php, array $settings = []): SystemInfo
	{
		$config = $this->config([
			...['db.dsn' => 'sqlite::memory:', 'path.public' => sys_get_temp_dir()],
			...$settings,
		]);

		$connection = self::connection($config);
		$db = new Database($connection);
		$db->execute('CREATE TABLE migrations (migration TEXT, applied TEXT)')->run();
		$db->execute('CREATE TABLE cms_jobs (state TEXT)')->run();

		return new SystemInfo($config, $db, $connection, $php);
	}

	private static function connection(Config $config): Connection
	{
		return new Connection((string) $config->db->dsn, self::root() . '/db/sql')
			->placeholders(Delimiters::comments(), $config->db->placeholders);
	}

	/**
	 * @param array{groups: list<array{entries: list<Entry>}>} $report
	 * @return Entry
	 */
	private static function entry(array $report, string $key): array
	{
		foreach ($report['groups'] as $group) {
			foreach ($group['entries'] as $entry) {
				if ($entry['key'] === $key) {
					return $entry;
				}
			}
		}

		self::fail("No entry {$key}");
	}
}
