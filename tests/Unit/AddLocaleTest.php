<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Celema\Core\Factory\Factory;
use Celema\Verba\Translator;
use Cosray\Locales;
use Cosray\Middleware\AddLocale;
use Cosray\Tests\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * @internal
 *
 * @coversNothing
 */
final class AddLocaleTest extends TestCase
{
	public function testTranslatorFollowsTheLocaleFallbackChain(): void
	{
		$locales = new Locales();
		$locales->add('en', title: 'English');
		$locales->add('es', title: 'Español', fallback: 'en');
		$locales->catalog('app', __DIR__ . '/../Fixtures/lang');

		$request = $this
			->factory()
			->serverRequestFactory()
			->createServerRequest('GET', '/')
			->withQueryParams(['locale' => 'es']);
		$handler = new class($this->factory()) implements RequestHandlerInterface {
			public ?ServerRequestInterface $request = null;

			public function __construct(
				private Factory $factory,
			) {}

			public function handle(ServerRequestInterface $request): ResponseInterface
			{
				$this->request = $request;

				return $this->factory->responseFactory()->createResponse();
			}
		};

		$middleware = new AddLocale($locales);
		$middleware->process($request, $handler);

		$this->assertNotNull($handler->request);
		$translator = $handler->request->getAttribute('translator');
		$this->assertInstanceOf(Translator::class, $translator);
		$this->assertSame('es', $translator->locale);
		$this->assertSame('Hola', $translator->translate('greet'));
		$this->assertSame('From the fallback', $translator->translate('shared'));
		$this->assertSame('missing', $translator->translate('missing'));
	}
}
