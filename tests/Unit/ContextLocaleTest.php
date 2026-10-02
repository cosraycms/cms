<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Celema\Core\Request;
use Cosray\Context;
use Cosray\Locales;
use Cosray\Tests\Fixtures\Context\RequestAware;
use Cosray\Tests\Fixtures\Context\RequestService;
use Cosray\Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class ContextLocaleTest extends TestCase
{
	public function testCodeRunInAnotherLocaleReadsItOffTheRequest(): void
	{
		$locales = new Locales();
		$locales->add('en', title: 'English');
		$locales->add('de', title: 'Deutsch', fallback: 'en');
		$request = new Request(
			$this
				->psrRequest()
				->withAttribute('locales', $locales)
				->withAttribute('locale', $locales->get('en')),
		);
		$container = $this->container();
		$container->add(RequestService::class);
		$context = new Context($this->db(), $request, $this->config(), $container, $this->factory());

		$seen = $context->withLocale($locales->get('de'), static fn(): array => [
			'locale' => $context->locale()->id,
			'request' => $context->httpRequest()->get('locale')->id,
			'created' => $context->create(RequestAware::class)->request->get('locale')->id,
			'original' => $request->get('locale')->id,
		]);

		$this->assertSame(['locale' => 'de', 'request' => 'de', 'created' => 'de', 'original' => 'en'], $seen);
		$this->assertSame($request, $context->httpRequest());
		$this->assertSame('en', $context->locale()->id);
	}
}
