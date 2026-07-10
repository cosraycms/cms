<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Locales;
use Cosray\Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class LocaleTest extends TestCase
{
	public function testFallbacksFollowsTheChain(): void
	{
		$locales = new Locales();
		$locales->add('es', title: 'Español', fallback: 'en');
		$locales->add('en', title: 'English', fallback: 'de');
		$locales->add('de', title: 'Deutsch');

		$this->assertSame(['en', 'de'], $locales->get('es')->fallbacks());
		$this->assertSame(['de'], $locales->get('en')->fallbacks());
		$this->assertSame([], $locales->get('de')->fallbacks());
	}

	public function testFallbacksStopsBeforeALocaleRepeats(): void
	{
		$locales = new Locales();
		$locales->add('es', title: 'Español', fallback: 'en');
		$locales->add('en', title: 'English', fallback: 'de');
		$locales->add('de', title: 'Deutsch', fallback: 'en');

		$this->assertSame(['en', 'de'], $locales->get('es')->fallbacks());
		$this->assertSame(['de'], $locales->get('en')->fallbacks());
	}

	public function testFallbacksIgnoresSelfReference(): void
	{
		$locales = new Locales();
		$locales->add('de', title: 'Deutsch', fallback: 'de');

		$this->assertSame([], $locales->get('de')->fallbacks());
	}
}
