<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Celemas\Core\Factory\Factory;
use Celemas\Verba\Translator;
use Celemas\Verba\Verba;
use Cosray\Locales;
use Cosray\Middleware\PanelLocale;
use Cosray\Tests\TestCase;
use Cosray\User;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * @internal
 *
 * @coversNothing
 */
final class PanelLocaleMiddlewareTest extends TestCase
{
	protected function tearDown(): void
	{
		Verba::deactivate();

		parent::tearDown();
	}

	public function testPanelLocalesListsIdsWithCosrayAndPanelCatalogs(): void
	{
		$this->assertSame(['de', 'en'], new Locales()->panelLocales());
	}

	public function testUserPreferenceWins(): void
	{
		$handler = $this->handler();
		$request = $this
			->panelRequest()
			->withAttribute('user', $this->user('de'))
			->withHeader('Accept-Language', 'en');

		new PanelLocale($this->config(['panel.locale' => 'en']))->process($request, $handler);

		$this->assertSame('de', $this->translator($handler)->locale);
		$this->assertSame('de', $handler->request?->getAttribute('panelLocale'));
		$this->assertSame(['de', 'en'], $handler->request?->getAttribute('panelLocales'));
	}

	public function testUnknownUserPreferenceFallsThrough(): void
	{
		$handler = $this->handler();
		$request = $this
			->panelRequest()
			->withAttribute('user', $this->user('tlh'))
			->withHeader('Accept-Language', 'de');

		new PanelLocale($this->config())->process($request, $handler);

		$this->assertSame('de', $this->translator($handler)->locale);
	}

	public function testConfiguredDefaultBeatsBrowser(): void
	{
		$handler = $this->handler();
		$request = $this->panelRequest()->withHeader('Accept-Language', 'en');

		new PanelLocale($this->config(['panel.locale' => 'de']))->process($request, $handler);

		$this->assertSame('de', $this->translator($handler)->locale);
	}

	public function testBrowserNegotiationRespectsQuality(): void
	{
		$handler = $this->handler();
		$request = $this
			->panelRequest()
			->withHeader('Accept-Language', 'fr-FR,fr;q=0.9,de-DE;q=0.8,en;q=0.7');

		new PanelLocale($this->config())->process($request, $handler);

		$this->assertSame('de', $this->translator($handler)->locale);
	}

	public function testUnmatchedBrowserDefaultsToEnglish(): void
	{
		$handler = $this->handler();
		$request = $this->panelRequest()->withHeader('Accept-Language', 'fr,it;q=0.9');

		new PanelLocale($this->config())->process($request, $handler);

		$this->assertSame('en', $this->translator($handler)->locale);
	}

	public function testTranslatesThroughTheActivatedTranslator(): void
	{
		$handler = new class($this->factory()) implements RequestHandlerInterface {
			public ?string $translated = null;

			public function __construct(
				private Factory $factory,
			) {}

			public function handle(ServerRequestInterface $request): ResponseInterface
			{
				$this->translated = __('nav:logout');

				return $this->factory->responseFactory()->createResponse();
			}
		};
		$request = $this->panelRequest()->withHeader('Accept-Language', 'de');

		new PanelLocale($this->config())->process($request, $handler);

		$this->assertSame('Abmelden', $handler->translated);
	}

	public function testRestoresThePreviousTranslator(): void
	{
		$content = new Translator('de', []);
		Verba::activate($content);
		$request = $this->panelRequest()->withHeader('Accept-Language', 'en');

		new PanelLocale($this->config())->process($request, $this->handler());

		$this->assertSame($content, Verba::translator());
	}

	public function testPassesThroughWithoutLocalesAttribute(): void
	{
		$handler = $this->handler();
		$request = $this
			->factory()
			->serverRequestFactory()
			->createServerRequest('GET', '/cp');

		new PanelLocale($this->config())->process($request, $handler);

		$this->assertNull($handler->request?->getAttribute('panelLocale'));
		$this->assertNull(Verba::translator());
	}

	private function panelRequest(): ServerRequestInterface
	{
		return $this
			->factory()
			->serverRequestFactory()
			->createServerRequest('GET', '/cp')
			->withAttribute('locales', new Locales());
	}

	private function user(?string $panelLocale): User
	{
		return new User([
			'usr' => 42,
			'uid' => 'editor',
			'username' => 'editor',
			'email' => 'editor@example.com',
			'password' => 'hash',
			'role' => 'editor',
			'active' => true,
			'panel_locale' => $panelLocale,
			'created' => '2024-01-01T00:00:00+00:00',
			'changed' => '2024-01-01T00:00:00+00:00',
			'deleted' => null,
			'expires' => null,
		]);
	}

	private function translator(object $handler): Translator
	{
		$translator = $handler->request?->getAttribute('translator');
		$this->assertInstanceOf(Translator::class, $translator);

		return $translator;
	}

	private function handler(): RequestHandlerInterface
	{
		return new class($this->factory()) implements RequestHandlerInterface {
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
	}
}
