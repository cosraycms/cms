<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Celema\Core\Factory\Factory;
use Celema\Verba\Translator;
use Celema\Verba\Verba;
use Cosray\Exception\RuntimeException;
use Cosray\Locales;
use Cosray\Middleware\PanelLocale;
use Cosray\Tests\TestCase;
use Cosray\User;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
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
		$locales = new Locales();
		$locales->catalog('cosray', dirname(__DIR__) . '/Fixtures/panel-locales');
		$locales->catalog('panel', dirname(__DIR__) . '/Fixtures/panel-locales');

		$this->assertSame(['de', 'en'], $locales->panelLocales());
	}

	public function testUserPreferenceWins(): void
	{
		$handler = $this->handler();
		$config = $this->config(['panel.locale' => 'en']);
		$request = $this
			->panelRequest()
			->withAttribute('user', $this->user('de'))
			->withCookieParams([PanelLocale::cookie($config) => 'en'])
			->withHeader('Accept-Language', 'en');

		new PanelLocale($config)->process($request, $handler);

		$this->assertSame('de', $this->translator($handler)->locale);
		$this->assertSame('de', $handler->request?->getAttribute('panelLocale'));
		$this->assertSame(new Locales()->panelLocales(), $handler->request?->getAttribute('panelLocales'));
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

	public function testTheLanguageChosenBeforeSigningInBeatsConfigAndBrowser(): void
	{
		$handler = $this->handler();
		$config = $this->config(['panel.locale' => 'en']);
		$request = $this
			->panelRequest()
			->withCookieParams([PanelLocale::cookie($config) => 'de'])
			->withHeader('Accept-Language', 'en');

		new PanelLocale($config)->process($request, $handler);

		$this->assertSame('de', $this->translator($handler)->locale);
	}

	public function testAnUnknownLanguageChosenBeforeSigningInFallsThrough(): void
	{
		$handler = $this->handler();
		$config = $this->config();
		$request = $this
			->panelRequest()
			->withCookieParams([PanelLocale::cookie($config) => 'tlh'])
			->withHeader('Accept-Language', 'de');

		new PanelLocale($config)->process($request, $handler);

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
			->withHeader('Accept-Language', 'tlh-XX,tlh;q=0.9,en;q=0.7,de-DE;q=0.8');

		new PanelLocale($this->config())->process($request, $handler);

		$this->assertSame('de', $this->translator($handler)->locale);
	}

	/** @return array<string, array{string, array<string, mixed>, string}> */
	public static function formatLocaleProvider(): array
	{
		return [
			'browser region of the panel language' => ['en-GB,en;q=0.9', [], 'en_GB'],
			'first tag with a region' => ['en,en-AU;q=0.9,en-GB;q=0.8', [], 'en_AU'],
			'region of another language ignored' => ['de-AT,en;q=0.9', ['panel.locale' => 'en'], 'en'],
			'configured region without a browser region' => ['en', ['panel.region' => 'gb'], 'en_GB'],
			'browser region before the configured one' => ['en-AU', ['panel.region' => 'GB'], 'en_AU'],
			'language default' => ['en', [], 'en'],
		];
	}

	/** @param array<string, mixed> $settings */
	#[DataProvider('formatLocaleProvider')]
	public function testFormatLocaleAddsTheRegion(string $accepted, array $settings, string $expected): void
	{
		$handler = $this->handler();
		$request = $this->panelRequest()->withHeader('Accept-Language', $accepted);

		new PanelLocale($this->config($settings))->process($request, $handler);

		$this->assertSame($expected, $handler->request?->getAttribute('formatLocale'));
	}

	public function testTheUsersRegionWinsOverTheDetectedOne(): void
	{
		$handler = $this->handler();
		$request = $this
			->panelRequest()
			->withAttribute('user', $this->user('en', 'AU'))
			->withHeader('Accept-Language', 'en-GB');

		new PanelLocale($this->config())->process($request, $handler);

		$this->assertSame('en_AU', $handler->request?->getAttribute('formatLocale'));
		$this->assertSame('en_GB', $handler->request?->getAttribute('detectedFormatLocale'));
	}

	public function testAnUnknownUserRegionFallsBackToTheDetectedOne(): void
	{
		$handler = $this->handler();
		$request = $this
			->panelRequest()
			->withAttribute('user', $this->user('en', 'QQ'))
			->withHeader('Accept-Language', 'en-GB');

		new PanelLocale($this->config())->process($request, $handler);

		$this->assertSame('en_GB', $handler->request?->getAttribute('formatLocale'));
	}

	public function testTimestampsFollowTheUsersTimezoneElseTheSites(): void
	{
		$config = $this->config(['app.timezone' => 'Europe/Berlin']);

		foreach ([
			['America/New_York', 'America/New_York'],
			['Mars/Olympus', 'Europe/Berlin'],
			[null, 'Europe/Berlin'],
		] as [$chosen, $expected]) {
			$handler = $this->handler();
			$request = $this->panelRequest()->withAttribute('user', $this->user('en', null, $chosen));

			new PanelLocale($config)->process($request, $handler);

			$timezone = $handler->request?->getAttribute('panelTimezone');
			$this->assertInstanceOf(DateTimeZone::class, $timezone);
			$this->assertSame($expected, $timezone->getName());
		}
	}

	public function testAnInvalidRegionIsAConfigurationError(): void
	{
		$this->expectException(RuntimeException::class);

		new PanelLocale($this->config(['panel.region' => 'Great Britain']))->process(
			$this->panelRequest()->withHeader('Accept-Language', 'en'),
			$this->handler(),
		);
	}

	public function testUnmatchedBrowserDefaultsToEnglish(): void
	{
		$handler = $this->handler();
		$request = $this->panelRequest()->withHeader('Accept-Language', 'tlh,qya;q=0.9');

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
			->createServerRequest('GET', '/panel');

		new PanelLocale($this->config())->process($request, $handler);

		$this->assertNull($handler->request?->getAttribute('panelLocale'));
		$this->assertNull(Verba::translator());
	}

	private function panelRequest(): ServerRequestInterface
	{
		return $this
			->factory()
			->serverRequestFactory()
			->createServerRequest('GET', '/panel')
			->withAttribute('locales', new Locales());
	}

	private function user(?string $panelLocale, ?string $region = null, ?string $timezone = null): User
	{
		return new User([
			'usr' => 42,
			'uid' => 'editor',
			'username' => 'editor',
			'email' => 'editor@example.com',
			'password' => 'hash',
			'roles' => ['editor'],
			'active' => true,
			'panel_locale' => $panelLocale,
			'panel_region' => $region,
			'panel_timezone' => $timezone,
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
