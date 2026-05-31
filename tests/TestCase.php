<?php

declare(strict_types=1);

namespace Cosray\Tests;

use Celemas\Container\Container;
use Celemas\Core\Factory\Factory;
use Celemas\Core\Factory\Laminas;
use Celemas\Core\Request;
use Celemas\Quma\Delimiters;
use Cosray\Config;
use Cosray\Locales;
use PDO;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Psr\Http\Message\ServerRequestInterface as PsrServerRequest;
use ValueError;

/**
 * Base test case for unit tests.
 *
 * @internal
 *
 * @coversNothing
 */
class TestCase extends BaseTestCase
{
	protected function setUp(): void
	{
		parent::setUp();

		$_SERVER['HTTP_ACCEPT'] = 'text/html,application/xhtml+xml,text/plain';
		$_SERVER['HTTP_ACCEPT_ENCODING'] = 'gzip, deflate, br';
		$_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en-US,de;q=0.7,en;q=0.3';
		$_SERVER['HTTP_HOST'] = 'www.example.com';
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:108.0) Gecko/20100101 Firefox/108.0';
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['REQUEST_URI'] = '/';
		$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
	}

	protected static function root(): string
	{
		return dirname(__DIR__);
	}

	protected static function testDbDsn(): string
	{
		$host = self::testDbSetting('COSRAY_DB_HOST', 'localhost');
		$database = self::testDbSetting('COSRAY_DB_NAME', 'cosray');
		$username = self::testDbSetting('COSRAY_DB_USER', 'cosray');
		$password = self::testDbSetting('COSRAY_DB_PASSWORD', 'cosray');

		return "pgsql:host={$host};dbname={$database};user={$username};password={$password}";
	}

	private static function testDbSetting(string $key, string $default): string
	{
		$value = $_SERVER[$key] ?? $_ENV[$key] ?? getenv($key);

		return is_string($value) && $value !== '' ? $value : $default;
	}

	public function throws(string $exception, ?string $message = null): void
	{
		$this->expectException($exception);

		if ($message) {
			$this->expectExceptionMessage($message);
		}
	}

	protected function tearDown(): void
	{
		unset(
			$_SERVER['CONTENT_TYPE'],
			$_SERVER['HTTPS'],
			$_SERVER['HTTP_ACCEPT'],
			$_SERVER['HTTP_ACCEPT_ENCODING'],
			$_SERVER['HTTP_ACCEPT_LANGUAGE'],
			$_SERVER['HTTP_HOST'],
			$_SERVER['HTTP_USER_AGENT'],
			$_SERVER['HTTP_X_FORWARDED_PROTO'],
			$_SERVER['QUERY_STRING'],
			$_SERVER['REQUEST_METHOD'],
			$_SERVER['REQUEST_SCHEME'],
			$_SERVER['REQUEST_URI'],
			$_SERVER['SERVER_PROTOCOL'],
			$_SERVER['argv'],
		);

		global $_GET;
		$_GET = [];
		global $_POST;
		$_POST = [];
		global $_FILES;
		$_FILES = [];
		global $_COOKIE;
		$_COOKIE = [];
	}

	public function setMethod(string $method): void
	{
		$_SERVER['REQUEST_METHOD'] = strtoupper($method);
	}

	public function setContentType(string $contentType): void
	{
		$_SERVER['HTTP_CONTENT_TYPE'] = $contentType;
	}

	public function setRequestUri(string $url): void
	{
		if (substr($url, 0, 1) === '/') {
			$_SERVER['REQUEST_URI'] = $url;
		} else {
			$_SERVER['REQUEST_URI'] = "/{$url}";
		}
	}

	public function setQueryString(string $qs): void
	{
		$_SERVER['QUERY_STRING'] = $qs;
	}

	public function config(array $settings = [], bool $debug = false): Config
	{
		return new Config(self::root(), array_merge([
			'app.name' => 'cosray',
			'app.debug' => $debug,
		], $settings));
	}

	public function request(
		?string $method = null,
		?string $url = null,
	): Request {
		if ($method) {
			$this->setMethod($method);
		}

		if ($url) {
			$this->setRequestUri($url);
		}

		return new Request($this->psrRequest());
	}

	public function container(): Container
	{
		return new Container();
	}

	/**
	 * Stub db() method for unit tests that need to create Context objects.
	 * This method creates a database instance but is not meant for actual database operations.
	 * For real database operations, use IntegrationTestCase instead.
	 */
	public function db(): \Celemas\Quma\Database
	{
		$config = new Config(self::root());

		return new \Celemas\Quma\Database(
			new \Celemas\Quma\Connection(
				self::testDbDsn(),
				self::root() . '/db/sql',
			)
				->placeholders(Delimiters::comments(), $config->db->placeholders)
				->migrations(self::root() . '/db/migrations')
				->fetch(PDO::FETCH_ASSOC),
		);
	}

	public function set(string $method, array $values): void
	{
		global $_GET;
		global $_POST;
		global $_COOKIE;

		foreach ($values as $key => $value) {
			if (strtoupper($method) === 'GET') {
				$_GET[$key] = $value;

				continue;
			}

			if (strtoupper($method) === 'POST') {
				$_POST[$key] = $value;

				continue;
			}

			if (strtoupper($method) === 'COOKIE') {
				$_COOKIE[$key] = $value;
			} else {
				throw new ValueError("Invalid method '{$method}'");
			}
		}
	}

	public function psrRequest(string $localeId = 'en'): PsrServerRequest
	{
		$request = $this->factory()->serverRequest();
		$locales = new Locales();
		$locales->add(
			'en',
			title: 'English',
			domains: ['www.example.com'],
		);
		$locales->add(
			'de',
			title: 'Deutsch',
			domains: ['www.example.de'],
			fallback: 'en',
		);
		$locales->add(
			'it',
			domains: ['www.example.it'],
			title: 'Italiano',
			fallback: 'en',
		);

		return $request
			->withAttribute('locales', $locales)
			->withAttribute('locale', $locales->get($localeId));
	}

	public function factory(): Factory
	{
		return new Laminas();
	}

	public function fullTrim(string $text): string
	{
		return trim(
			preg_replace(
				'/> </',
				'><',
				preg_replace(
					'/\s+/',
					' ',
					preg_replace('/\n/', '', $text),
				),
			),
		);
	}
}
