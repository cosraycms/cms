<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Celemas\Core\Exception\ValueError;
use Cosray\Config;
use Cosray\Exception\RuntimeException;
use Cosray\Tests\TestCase;
use Cosray\Util\Password;
use Dotenv\Exception\ValidationException;

/**
 * @internal
 *
 * @coversNothing
 */
final class ConfigTest extends TestCase
{
	/** @var array<string, array{env: bool, envValue: mixed, server: bool, serverValue: mixed, process: bool, processValue: string|null}> */
	private array $environment = [];

	/** @var list<string> */
	private array $roots = [];

	protected function setUp(): void
	{
		parent::setUp();

		$this->clearEnvironment(
			'APP_DEBUG',
			'APP_ENV',
			'APP_MISSING',
			'APP_NAME',
			'APP_REQUIRED',
			'APP_SECRET',
			'CMS_DSN',
			'DATABASE_URL',
			'SITE_SESSION_ENABLED',
			'SESSION_COOKIE_LIFETIME',
			'SESSION_COOKIE_SECURE',
			'SESSION_IDLE_TIMEOUT',
		);
	}

	protected function tearDown(): void
	{
		foreach ($this->roots as $root) {
			if (is_file($root . '/.env')) {
				unlink($root . '/.env');
			}

			if (is_dir($root)) {
				rmdir($root);
			}
		}

		foreach ($this->environment as $key => $value) {
			if ($value['env']) {
				$_ENV[$key] = $value['envValue'];
			} else {
				unset($_ENV[$key]);
			}

			if ($value['server']) {
				$_SERVER[$key] = $value['serverValue'];
			} else {
				unset($_SERVER[$key]);
			}

			if ($value['process']) {
				putenv($key . '=' . $value['processValue']);
			} else {
				putenv($key);
			}
		}

		parent::tearDown();
	}

	public function testDefaultsUseRootAndDefaultAppName(): void
	{
		$config = new Config(self::root());

		$this->assertSame('cosray', $config->get('app.name'));
		$this->assertSame('cosray', $config->app->name);
		$this->assertSame(self::root(), $config->path->root);
		$this->assertSame(self::root() . '/public', $config->path->public);
		$this->assertNull($config->app->secret);
		$this->assertSame([], $config->panel->theme);
		$this->assertFalse($config->session->enabled);
		$this->assertSame(0, $config->session->options['cookie_lifetime']);
		$this->assertTrue($config->session->options['cookie_secure']);
		$this->assertSame(3600, $config->session->options['gc_maxlifetime']);
		$this->assertSame(3600, $config->session->options['cache_expire']);
		$this->assertNull($config->session->handler);
		$this->assertNull($config->db->dsn);
		$this->assertSame(Password::DEFAULT_PASSWORD_ENTROPY, $config->password->entropy);
		$this->assertNull($config->password->algorithm);
		$this->assertFalse($config->debug());
		$this->assertSame('', $config->env());
	}

	public function testSettingsCanOverrideAppNameDebugAndEnvironment(): void
	{
		$config = new Config(self::root(), [
			'app.name' => 'site-cms',
			'app.debug' => false,
			'app.env' => 'production',
			'app.secret' => 'configured-secret',
			'session.enabled' => true,
		]);

		$this->assertSame('site-cms', $config->app->name);
		$this->assertFalse($config->debug());
		$this->assertSame('production', $config->env());
		$this->assertSame('configured-secret', $config->app->secret);
		$this->assertTrue($config->session->enabled);
	}

	public function testConstructorRequiresRoot(): void
	{
		$this->throws(ValueError::class, 'The root path must be a non-empty string.');

		new Config('');
	}

	public function testPanelPathComesFromExplicitConfigInCmsDevelopmentEnvironment(): void
	{
		$config = new Config(self::root(), [
			'app.env' => 'cms-development',
			'path.panel' => '/admin',
		]);

		$this->assertSame('/admin', $config->panel->path);
	}

	public function testDotenvIsLoadedFromRoot(): void
	{
		$root = $this->rootWithEnv(
			"APP_NAME=test-cms\nAPP_DEBUG=true\nAPP_ENV=testing\nAPP_REQUIRED=present\nAPP_SECRET=test-secret\nSITE_SESSION_ENABLED=true\n",
		);
		$config = new Config($root);

		$this->assertSame('test-cms', $config->app->name);
		$this->assertTrue($config->debug());
		$this->assertSame('testing', $config->env());
		$this->assertSame('test-secret', $config->app->secret);
		$this->assertTrue($config->session->enabled);
		$this->assertSame('present', $_ENV['APP_REQUIRED']);
	}

	public function testSessionOptionsCanBeChangedFromEnvironment(): void
	{
		$config = new Config($this->rootWithEnv(
			"SESSION_COOKIE_SECURE=false\nSESSION_COOKIE_LIFETIME=86400\nSESSION_IDLE_TIMEOUT=7200\n",
		));

		$this->assertFalse($config->session->options['cookie_secure']);
		$this->assertSame(86400, $config->session->options['cookie_lifetime']);
		$this->assertSame(7200, $config->session->options['gc_maxlifetime']);
	}

	public function testSessionOptionsAreDeepMerged(): void
	{
		$config = new Config(self::root(), [
			'session.options' => [
				'cookie_secure' => false,
			],
		]);

		$this->assertTrue($config->session->options['cookie_httponly']);
		$this->assertFalse($config->session->options['cookie_secure']);
		$this->assertSame(0, $config->session->options['cookie_lifetime']);
		$this->assertSame(3600, $config->session->options['gc_maxlifetime']);
		$this->assertSame(3600, $config->session->options['cache_expire']);
	}

	public function testDatabasePlaceholdersAreDeepMerged(): void
	{
		$config = new Config(self::root(), [
			'db.placeholders' => [
				'all' => [
					'app.prefix' => 'app_',
				],
				'pgsql' => [
					'app.prefix' => 'app.',
				],
			],
		]);

		$this->assertSame(
			[
				'all' => [
					'cms.prefix' => 'cms_',
					'app.prefix' => 'app_',
				],
				'pgsql' => [
					'cms.prefix' => 'cms.',
					'app.prefix' => 'app.',
					'cms.obj' => '',
				],
			],
			$config->db->placeholders,
		);
	}

	public function testPostgresqlObjectPrefixIsDerivedFromCmsPrefix(): void
	{
		$config = new Config(self::root(), [
			'db.dsn' => 'pgsql:dbname=cms',
			'db.placeholders' => [
				'pgsql' => [
					'cms.prefix' => 'cms_',
				],
			],
		]);

		$this->assertSame('cms_', $config->db->placeholders['pgsql']['cms.obj']);

		$config = new Config(self::root(), [
			'db.dsn' => 'pgsql:dbname=cms',
		]);

		$this->assertSame('', $config->db->placeholders['pgsql']['cms.obj']);

		$config = new Config(self::root(), [
			'db.dsn' => 'pgsql:dbname=cms',
			'db.placeholders' => [
				'pgsql' => [
					'cms.prefix' => '',
				],
			],
		]);

		$this->assertSame('', $config->db->placeholders['pgsql']['cms.obj']);
	}

	public function testPostgresqlObjectPrefixCanBeOverridden(): void
	{
		$config = new Config(self::root(), [
			'db.dsn' => 'pgsql:dbname=cms',
			'db.placeholders' => [
				'pgsql' => [
					'cms.prefix' => 'cms.',
					'cms.obj' => 'custom_',
				],
			],
		]);

		$this->assertSame('custom_', $config->db->placeholders['pgsql']['cms.obj']);

		$config = new Config(self::root(), [
			'db.dsn' => 'pgsql:dbname=cms',
			'db.placeholders' => [
				'all' => [
					'cms.obj' => 'shared_',
				],
			],
		]);

		$this->assertSame('shared_', $config->db->placeholders['all']['cms.obj']);
		$this->assertSame('', $config->db->placeholders['pgsql']['cms.obj']);
	}

	public function testDatabasePlaceholdersRequireCmsPrefix(): void
	{
		$config = new Config(self::root(), [
			'db.dsn' => 'pgsql:dbname=cms',
			'db.placeholders' => [],
		]);

		$this->throws(
			RuntimeException::class,
			'Invalid table prefix.',
		);

		$config->db->placeholders;
	}

	public function testDatabasePlaceholdersRequireValidCmsPrefix(): void
	{
		$config = new Config(self::root(), [
			'db.dsn' => 'pgsql:dbname=cms',
			'db.placeholders' => [
				'pgsql' => [
					'cms.prefix' => 'cms-',
				],
			],
		]);

		$this->throws(
			RuntimeException::class,
			'Invalid table prefix.',
		);

		$config->db->placeholders;
	}

	public function testDatabasePlaceholdersAllowDotOnlyForPostgresql(): void
	{
		$config = new Config(self::root(), [
			'db.dsn' => 'mysql:dbname=cms',
			'db.placeholders' => [
				'all' => [
					'cms.prefix' => 'cms.',
				],
			],
		]);

		$this->throws(
			RuntimeException::class,
			'Invalid table prefix.',
		);

		$config->db->placeholders;
	}

	public function testListSettingsAreConvertedByConfigObjects(): void
	{
		$config = new Config(self::root(), [
			'panel.theme' => '/theme.css',
			'db.sql' => '/sql',
			'db.migrations' => ['/migrations'],
			'icons.local.paths' => '/icons',
		]);

		$this->assertSame(['/theme.css'], $config->panel->theme);
		$this->assertSame(['/sql'], $config->db->sql);
		$this->assertSame(['/migrations'], $config->db->migrations);
		$this->assertSame(['/icons'], $config->icons->localPaths);
	}

	public function testTypedConfigPropertiesFailOnMisconfiguration(): void
	{
		$config = new Config(self::root(), [
			'session.enabled' => 'true',
		]);
		$session = $config->session;

		$this->throws(\TypeError::class);

		$session->enabled;
	}

	public function testConfigObjectsAreLazy(): void
	{
		$config = new Config(self::root());

		$this->assertSame($config->app, $config->app);
		$this->assertSame($config->path, $config->path);
		$this->assertSame($config->panel, $config->panel);
		$this->assertSame($config->session, $config->session);
	}

	public function testWithReturnsChangedConfig(): void
	{
		$config = new Config(self::root(), [
			'panel.theme' => '/theme.css',
		]);
		$changed = $config->with('panel.theme', '/changed.css');

		$this->assertNotSame($config, $changed);
		$this->assertSame(['/theme.css'], $config->panel->theme);
		$this->assertSame(['/changed.css'], $changed->panel->theme);
	}

	public function testUnknownKeysStillWorkAtRuntime(): void
	{
		$config = new Config(self::root(), [
			'custom.value' => 3,
		]);

		$changed = $config->with('custom.other', ['enabled' => true]);

		$this->assertSame(3, $config->get('custom.value'));
		$this->assertSame(3, $changed->get('custom.value'));
		$this->assertSame(['enabled' => true], $changed->get('custom.other'));
	}

	public function testDatabaseDsnUsesEnvironmentVariable(): void
	{
		$config = new Config($this->rootWithEnv("DATABASE_URL=pgsql:dbname=cms\n"));

		$this->assertSame('pgsql:dbname=cms', $config->db->dsn);
	}

	public function testMissingDotenvFileIsIgnored(): void
	{
		$config = new Config($this->rootWithEnv());

		$this->assertFalse($config->debug());
	}

	public function testRequireEnvReturnsConfigWhenVariableExists(): void
	{
		$config = new Config($this->rootWithEnv("APP_REQUIRED=present\n"));

		$this->assertSame($config, $config->requireEnv('APP_REQUIRED'));
	}

	public function testRequireEnvAcceptsServerEnvironmentVariable(): void
	{
		$_SERVER['APP_REQUIRED'] = 'present';
		$config = new Config($this->rootWithEnv());

		$this->assertSame($config, $config->requireEnv('APP_REQUIRED'));
	}

	public function testInvalidBooleanEnvironmentVariableFails(): void
	{
		$this->throws(ValidationException::class);

		new Config($this->rootWithEnv("SITE_SESSION_ENABLED=maybe\n"));
	}

	public function testInvalidIntegerEnvironmentVariableFails(): void
	{
		$this->throws(ValidationException::class);

		new Config($this->rootWithEnv("SESSION_IDLE_TIMEOUT=forever\n"));
	}

	public function testRequireEnvFailsForMissingVariable(): void
	{
		$config = new Config($this->rootWithEnv());

		$this->throws(ValidationException::class);

		$config->requireEnv('APP_MISSING');
	}

	private function clearEnvironment(string ...$keys): void
	{
		foreach ($keys as $key) {
			$processValue = getenv($key);
			$this->environment[$key] = [
				'env' => array_key_exists($key, $_ENV),
				'envValue' => $_ENV[$key] ?? null,
				'server' => array_key_exists($key, $_SERVER),
				'serverValue' => $_SERVER[$key] ?? null,
				'process' => $processValue !== false,
				'processValue' => $processValue === false ? null : $processValue,
			];

			unset($_ENV[$key], $_SERVER[$key]);
			putenv($key);
		}
	}

	private function rootWithEnv(string $contents = ''): string
	{
		$root = sys_get_temp_dir() . '/cosray-cms-config-' . bin2hex(random_bytes(4));
		mkdir($root);
		$this->roots[] = $root;

		if ($contents !== '') {
			file_put_contents($root . '/.env', $contents);
		}

		return $root;
	}
}
