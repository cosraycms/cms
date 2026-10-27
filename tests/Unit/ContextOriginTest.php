<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Celema\Core\Request;
use Cosray\Context;
use Cosray\Locales;
use Cosray\Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class ContextOriginTest extends TestCase
{
	public function testARequestKeepsItsOriginForItsOwnLocale(): void
	{
		// The request's host is none of the locale's domains, yet it answered the request in it.
		$context = $this->requestContext('http://localhost:8080/de', 'de');

		$this->assertSame('http://localhost:8080', $context->origin());
	}

	public function testAnotherLocaleWithDomainsGetsItsFirstOne(): void
	{
		$context = $this->requestContext('https://www.example.com:8443/', 'en');

		$this->assertSame('https://www.example.de:8443', $this->originIn($context, 'de'));
		$this->assertSame('https://www.example.com:8443', $this->originIn($context, 'en'));
		$this->assertSame('https://www.example.com:8443', $this->originIn($context, 'fr'));
		$this->assertSame('https://www.example.com:8443', $context->origin());
	}

	public function testAnotherLocaleKeepsAHostAmongItsDomains(): void
	{
		$context = $this->requestContext('https://example.de/', 'en');

		$this->assertSame('https://example.de', $this->originIn($context, 'de'));
	}

	public function testWithoutARequestTheAppUrlStandsIn(): void
	{
		$this->assertSame('https://example.org', $this->consoleContext('https://example.org/site/', 'fr')->origin());
		$this->assertSame('', $this->consoleContext(null, 'fr')->origin());

		$context = $this->consoleContext('http://localhost:8080', 'en');
		$this->assertSame('http://www.example.com:8080', $context->origin());
		$this->assertSame('http://www.example.de:8080', $this->originIn($context, 'de'));
		$this->assertSame('http://localhost:8080', $this->originIn($context, 'fr'));
	}

	/** The default locale comes first. */
	private function locales(string $default): Locales
	{
		$domains = [
			'en' => ['www.example.com'],
			'de' => ['www.example.de', 'example.de'],
			'fr' => null,
		];
		$locales = new Locales();

		foreach ([$default => $domains[$default]] + $domains as $id => $hosts) {
			$locales->add($id, title: $id, domains: $hosts);
		}

		return $locales;
	}

	private function requestContext(string $url, string $locale): Context
	{
		$locales = $this->locales('en');
		$psr = $this->factory()->serverRequestFactory()->createServerRequest('GET', $url);
		$request = new Request($psr->withAttribute('locales', $locales)->withAttribute(
			'locale',
			$locales->get($locale),
		));

		return new Context($this->db(), $request, $this->config(), $this->container(), $this->factory());
	}

	private function consoleContext(?string $url, string $default): Context
	{
		$config = $this->config(['app.url' => $url]);

		return Context::console($this->db(), $config, $this->container(), $this->factory(), $this->locales($default));
	}

	private function originIn(Context $context, string $locale): string
	{
		return $context->withLocale($context->locales()->get($locale), $context->origin(...));
	}
}
