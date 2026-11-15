<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Panel\Formats;
use Cosray\Tests\TestCase;

final class PanelFormatsTest extends TestCase
{
	public function testRegionsAreThoseWithFormatsInTheLanguageSortedByName(): void
	{
		$regions = Formats::regions('de', 'de');

		$this->assertSame('Österreich', $regions['AT']);
		$this->assertSame(['BE', 'DE', 'IT', 'LI', 'LU', 'AT', 'CH'], array_keys($regions));
		$this->assertArrayNotHasKey('US', $regions);
	}

	public function testRegionsAreNamedInThePanelLanguage(): void
	{
		$this->assertSame('United Kingdom', Formats::regions('en', 'en')['GB']);
		$this->assertSame('Vereinigtes Königreich', Formats::regions('en', 'de')['GB']);
	}

	public function testOnlyKnownRegionsAreValid(): void
	{
		$this->assertSame('GB', Formats::region('gb'));
		$this->assertSame('150', Formats::region('150'));
		$this->assertNull(Formats::region('QQ'));
		$this->assertNull(Formats::region('Great Britain'));
		$this->assertNull(Formats::region(null));
	}

	public function testOnlyKnownTimezonesAreValid(): void
	{
		$this->assertSame('Europe/Berlin', Formats::timezone('Europe/Berlin')?->getName());
		$this->assertNull(Formats::timezone('Mars/Olympus'));
		$this->assertNull(Formats::timezone(null));
	}
}
