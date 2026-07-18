<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Celema\Core\Exception\ValueError;
use Cosray\Config;
use Cosray\Exception\InvalidEnvironment;
use Cosray\Exception\RuntimeException;
use Cosray\Tests\TestCase;
use Cosray\Uid;
use Cosray\Util\Password;

/**
 * @internal
 *
 * @coversNothing
 */
final class ConfigTest extends TestCase
{
	/** @var array<string, array{env: bool, envValue: mixed, server: bool, serverValue: mixed, process: bool, processValue: string|null}> */
	private array $environment = [];

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
			'APP_TIMEZONE',
			'AUTH_REMEMBER_LIFETIME',
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
		$this->assertSame('/cp', $config->panel->path);
		$this->assertNull($config->app->secret);
		$this->assertSame('UTC', $config->app->timezone->getName());
		$this->assertSame(60 * 60 * 24 * 30, $config->auth->rememberLifetime);
		$this->assertSame([], $config->panel->theme);
		$this->assertFalse($config->session->enabled);
		$this->assertSame(0, $config->session->options['cookie_lifetime']);
		$this->assertSame('Lax', $config->session->options['cookie_samesite']);
		$this->assertTrue($config->session->options['cookie_secure']);
		$this->assertSame(60 * 60 * 8, $config->session->options['gc_maxlifetime']);
		$this->assertSame(3600, $config->session->options['cache_expire']);
		$this->assertNull($config->session->handler);
		$this->assertNull($config->db->dsn);
		$this->assertSame(Uid::ALPHABET_LOWERCASE_WORD_SAFE, $config->uid->alphabet);
		$this->assertSame(13, $config->uid->length);
		$this->assertSame(Password::DEFAULT_PASSWORD_ENTROPY, $config->password->entropy);
		$this->assertNull($config->password->algorithm);
		$this->assertFalse($config->debug());
		$this->assertSame('', $config->env());
	}

	public function testUidConfigCanBeOverridden(): void
	{
		$config = new Config(self::root(), [
			'uid.alphabet' => 'ab',
			'uid.length' => 4,
		]);

		$this->assertSame('ab', $config->uid->alphabet);
		$this->assertSame(4, $config->uid->length);
	}

	public function testSettingsCanOverrideAppNameDebugAndEnvironment(): void
	{
		$config = new Config(self::root(), [
			'app.name' => 'site-cms',
			'app.debug' => false,
			'app.env' => 'production',
			'app.secret' => 'configured-secret',
			'app.timezone' => 'Europe/Berlin',
			'auth.remember_lifetime' => 60 * 60 * 24 * 7,
			'session.enabled' => true,
		]);

		$this->assertSame('site-cms', $config->app->name);
		$this->assertFalse($config->debug());
		$this->assertSame('production', $config->env());
		$this->assertSame('configured-secret', $config->app->secret);
		$this->assertSame('Europe/Berlin', $config->app->timezone->getName());
		$this->assertSame(60 * 60 * 24 * 7, $config->auth->rememberLifetime);
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

	public function testEnvironmentVariablesConfigureApp(): void
	{
		$this->setEnvironment([
			'APP_NAME' => 'test-cms',
			'APP_DEBUG' => 'true',
			'APP_ENV' => 'testing',
			'APP_SECRET' => 'test-secret',
			'APP_TIMEZONE' => 'Europe/Berlin',
			'SITE_SESSION_ENABLED' => 'true',
		]);
		$config = new Config(self::root());

		$this->assertSame('test-cms', $config->app->name);
		$this->assertTrue($config->debug());
		$this->assertSame('testing', $config->env());
		$this->assertSame('test-secret', $config->app->secret);
		$this->assertSame('Europe/Berlin', $config->app->timezone->getName());
		$this->assertTrue($config->session->enabled);
	}

	public function testSessionOptionsCanBeChangedFromEnvironment(): void
	{
		$this->setEnvironment([
			'AUTH_REMEMBER_LIFETIME' => '1209600',
			'SESSION_COOKIE_SECURE' => 'false',
			'SESSION_COOKIE_LIFETIME' => '86400',
			'SESSION_IDLE_TIMEOUT' => '7200',
		]);
		$config = new Config(self::root());

		$this->assertSame(1_209_600, $config->auth->rememberLifetime);
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
		$this->assertSame('Lax', $config->session->options['cookie_samesite']);
		$this->assertFalse($config->session->options['cookie_secure']);
		$this->assertSame(0, $config->session->options['cookie_lifetime']);
		$this->assertSame(60 * 60 * 8, $config->session->options['gc_maxlifetime']);
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

	public function testDatabaseTableUsesConfiguredPrefix(): void
	{
		$config = new Config(self::root(), [
			'db.dsn' => 'pgsql:dbname=cms',
		]);

		$this->assertSame('cms.nodes', $config->db->table('nodes'));

		$config = new Config(self::root(), [
			'db.dsn' => 'pgsql:dbname=cms',
			'db.placeholders' => [
				'pgsql' => [
					'cms.prefix' => '',
				],
			],
		]);

		$this->assertSame('url_paths', $config->db->table('url_paths'));
	}

	public function testDatabaseTableRequiresValidName(): void
	{
		$config = new Config(self::root());

		$this->throws(
			RuntimeException::class,
			'Invalid table name.',
		);

		$config->db->table('bad.table');
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
		$this->assertSame($config->auth, $config->auth);
		$this->assertSame($config->path, $config->path);
		$this->assertSame($config->panel, $config->panel);
		$this->assertSame($config->session, $config->session);
		$this->assertSame($config->uid, $config->uid);
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
		$this->setEnvironment(['DATABASE_URL' => 'pgsql:dbname=cms']);
		$config = new Config(self::root());

		$this->assertSame('pgsql:dbname=cms', $config->db->dsn);
	}

	public function testMissingEnvironmentUsesDefaults(): void
	{
		$config = new Config(self::root());

		$this->assertFalse($config->debug());
	}

	public function testRequireEnvReturnsConfigWhenVariableExists(): void
	{
		$this->setEnvironment(['APP_REQUIRED' => 'present']);
		$config = new Config(self::root());

		$this->assertSame($config, $config->requireEnv('APP_REQUIRED'));
	}

	public function testRequireEnvAcceptsServerEnvironmentVariable(): void
	{
		$_SERVER['APP_REQUIRED'] = 'present';
		$config = new Config(self::root());

		$this->assertSame($config, $config->requireEnv('APP_REQUIRED'));
	}

	public function testInvalidBooleanEnvironmentVariableFails(): void
	{
		$this->setEnvironment(['SITE_SESSION_ENABLED' => 'maybe']);

		$this->throws(InvalidEnvironment::class);

		new Config(self::root());
	}

	public function testInvalidIntegerEnvironmentVariableFails(): void
	{
		$this->setEnvironment(['SESSION_IDLE_TIMEOUT' => 'forever']);

		$this->throws(InvalidEnvironment::class);

		new Config(self::root());
	}

	public function testRequireEnvFailsForMissingVariable(): void
	{
		$config = new Config(self::root());

		$this->throws(InvalidEnvironment::class);

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

	/** @param array<string, string> $variables */
	private function setEnvironment(array $variables): void
	{
		foreach ($variables as $key => $value) {
			$_SERVER[$key] = $value;
		}
	}
}
