<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Celemas\Core\App as CoreApp;
use Celemas\Core\Error\Handler as ErrorHandler;
use Celemas\Core\Exception\ValueError;
use Celemas\Core\Plugin as CorePlugin;
use Celemas\Core\Response as CoreResponse;
use Celemas\Router\Router;
use Cosray\App;
use Cosray\Config;
use Cosray\Middleware\Session as SessionMiddleware;
use Cosray\Plugin;
use Cosray\Tests\Fixtures\Collection\TestArticlesCollection;
use Cosray\Tests\Fixtures\StaticRenderer;
use Cosray\Tests\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\AbstractLogger;

/**
 * @internal
 *
 * @coversNothing
 */
final class AppTest extends TestCase
{
	public function testCreateHelperBuildsCoreAppAndPlugin(): void
	{
		$app = App::create(self::root(), [
			'app.name' => 'test-cms',
			'custom.value' => 3,
			'error.enabled' => false,
		]);

		$this->assertSame($app->config, $app->config());
		$this->assertSame('test-cms', $app->config->app->name);
		$this->assertSame(self::root(), $app->config->path->root);
		$this->assertSame(3, $app->config->get('custom.value'));
		$this->assertInstanceOf(CoreApp::class, $app->core());
		$this->assertInstanceOf(Plugin::class, $app->plugin());
	}

	public function testCreateHelperRequiresRoot(): void
	{
		$this->throws(ValueError::class, 'The root path must be a non-empty string.');

		App::create('');
	}

	public function testInstallsErrorHandlerByDefault(): void
	{
		$app = $this->app(['error.enabled' => true]);

		try {
			$this->assertInstanceOf(ErrorHandler::class, $app->core()->errorHandler());
			$this->assertSame([], $app->getMiddleware());
		} finally {
			$this->restoreErrorHandler($app);
		}
	}

	public function testErrorHandlerCanBeDisabled(): void
	{
		$app = $this->app(['error.enabled' => false]);

		$this->assertNull($app->core()->errorHandler());
		$this->assertSame([], $app->getMiddleware());
	}

	public function testErrorHandlerDoesNotUseViewRenderer(): void
	{
		$app = $this->app([
			'error.enabled' => true,
			'path.root' => self::root(),
			'path.views' => '/missing-views',
		]);
		$app->renderer('view', StaticRenderer::class);
		$app->get('/boom', static function (): void {
			throw new \RuntimeException('Boom');
		});
		$request = $app->factory()->serverRequestFactory()->createServerRequest('GET', '/boom');
		ob_start();

		try {
			$response = $app->run($request);
			$output = ob_get_contents();
		} finally {
			ob_end_clean();
			$this->restoreErrorHandler($app);
		}

		$this->assertInstanceOf(ResponseInterface::class, $response);
		$this->assertStringContainsString('Internal Server Error', $output);
		$this->assertStringNotContainsString('custom:http-server-error', $output);
	}

	public function testErrorHandlerUsesRegisteredLogger(): void
	{
		$logger = new class extends AbstractLogger {
			/** @var list<array{level: mixed, message: string}> */
			public array $records = [];

			public function log(mixed $level, string|\Stringable $message, array $context = []): void
			{
				$this->records[] = [
					'level' => $level,
					'message' => (string) $message,
				];
			}
		};
		$app = $this->app([
			'error.enabled' => true,
			'path.root' => self::root(),
			'path.views' => '/missing-views',
		]);
		$app->logger($logger);
		$app->get('/boom', static function (): void {
			throw new \RuntimeException('Boom');
		});
		$request = $app->factory()->serverRequestFactory()->createServerRequest('GET', '/boom');
		ob_start();

		try {
			$app->run($request);
		} finally {
			ob_end_clean();
			$this->restoreErrorHandler($app);
		}

		$this->assertNotSame([], $logger->records);
	}

	public function testCmsMethodsConfigureInternalPlugin(): void
	{
		$app = $this->app();

		$app->section('Content')->collection(TestArticlesCollection::class);
		$app->node(\Cosray\Tests\Fixtures\Node\TestArticle::class);

		$this->assertArrayHasKey('test-articles', $app->navigation()->refs());
		$this->assertSame(
			'test-article',
			$app->meta()->get(\Cosray\Tests\Fixtures\Node\TestArticle::class, 'handle'),
		);
	}

	public function testBootLoadsCmsPluginAndCatchallRoute(): void
	{
		$app = $this->app();

		$this->assertFalse($app->container()->has(Config::class));

		$app->boot()->boot();
		$match = $app->router()->match(
			$app->factory()->serverRequestFactory()->createServerRequest('GET', '/missing'),
		);

		$this->assertSame($app->config(), $app->container()->get(Config::class));
		$this->assertSame('cms.catchall', $match->route()->name());
	}

	public function testSessionMiddlewareCanBeEnabledInConfig(): void
	{
		$app = $this->app(['session.enabled' => true]);
		$app->boot();
		$match = $app->router()->match(
			$app->factory()->serverRequestFactory()->createServerRequest('GET', '/missing'),
		);

		$this->assertTrue($this->hasSessionMiddleware($match->route()->getMiddleware()));
	}

	public function testCoreMethodsDelegateToInternalCoreApp(): void
	{
		$app = $this->app(['path.prefix' => '/site']);
		$app->get(
			'/ok',
			static fn(): CoreResponse => CoreResponse::create($app->factory())->body('ok'),
		);

		$request = $app->factory()->serverRequestFactory()->createServerRequest('GET', '/site/ok');
		ob_start();

		try {
			$response = $app->run($request);
			$output = ob_get_contents();
		} finally {
			ob_end_clean();
		}

		$this->assertInstanceOf(ResponseInterface::class, $response);
		$this->assertSame('ok', $output);
		$this->assertSame($app->config(), $app->container()->get(Config::class));
	}

	public function testLoadDelegatesToCoreApp(): void
	{
		$plugin = new class implements CorePlugin {
			public function load(CoreApp $app): void
			{
				$app->register('custom.service', self::class)->value();
			}
		};
		$app = $this->app();

		$app->load($plugin);

		$this->assertSame($plugin::class, $app->container()->get('custom.service'));
	}

	/** @param list<object> $middleware */
	private function hasSessionMiddleware(array $middleware): bool
	{
		foreach ($middleware as $entry) {
			if ($entry instanceof SessionMiddleware) {
				return true;
			}
		}

		return false;
	}

	private function restoreErrorHandler(App $app): void
	{
		$app->core()->errorHandler()?->restoreHandlers();
	}

	private function app(array $settings = []): App
	{
		$config = $this->appConfig($settings);

		return new App(
			$config,
			$this->factory(),
			new Router($config->path->prefix),
			$this->container(),
		);
	}

	private function appConfig(array $settings = []): Config
	{
		return $this->config(array_merge([
			'db.dsn' => 'pgsql:dbname=cosray',
			'error.enabled' => false,
			'path.root' => self::root() . '/tests/Fixtures/Boiler',
			'path.views' => '/templates',
		], $settings));
	}
}
