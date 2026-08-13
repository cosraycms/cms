<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Cosray\Bootstrap;
use Cosray\Config;
use Cosray\Tests\End2EndTestCase;
use Cosray\Tests\Fixtures\Collection\TestArticlesCollection;

/**
 * The masthead's three areas and the rail that belongs to one of them.
 *
 * @internal
 *
 * @coversNothing
 */
final class PanelAreasTest extends End2EndTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixtures('basic-types');
		$this->authenticateAs('editor');
	}

	protected function createBootstrap(Config $config): Bootstrap
	{
		$plugin = parent::createBootstrap($config);
		$plugin->section('Inhalt')->collection(TestArticlesCollection::class);

		return $plugin;
	}

	public function testTheCollectionRailBelongsToContentAlone(): void
	{
		$this->assertStringContainsString(
			'class="cms-sidebar"',
			$this->html('/cp/collection/test-articles'),
		);
		$this->assertStringNotContainsString('class="cms-sidebar"', $this->html('/cp'));
		$this->assertStringNotContainsString('class="cms-sidebar"', $this->html('/cp/media'));
	}

	public function testEachAreaMarksItselfCurrent(): void
	{
		$dashboard = $this->html('/cp');
		$this->assertStringContainsString('aria-current', $this->area($dashboard, '/cp'));
		$this->assertStringNotContainsString('aria-current', $this->area($dashboard, '/cp/media'));

		$media = $this->html('/cp/media');
		$this->assertStringContainsString('aria-current', $this->area($media, '/cp/media'));
		$this->assertStringNotContainsString('aria-current', $this->area($media, '/cp'));

		// Content is current for the whole area, not for one collection URL:
		// the entry points at the first collection and a second one is open.
		$collection = $this->html('/cp/collection/test-articles');
		$this->assertStringContainsString(
			'aria-current',
			$this->area($collection, '/cp/collection/test-articles'),
		);
		$this->assertStringNotContainsString('aria-current', $this->area($collection, '/cp'));
	}

	public function testTheContentEntryIsLabelledAndOpensTheFirstRailEntry(): void
	{
		$html = $this->html('/cp');

		$this->assertSame(3, substr_count($html, 'class="area"'));
		$this->assertMatchesRegularExpression(
			'/<a\s[^>]*class="area"[^>]*href="\/cp\/collection\/test-articles"[^>]*>\s*Content\s*<\/a>/',
			$html,
		);

		// Boosted navigation re-marks the nav from the URL alone, so the entry
		// carries the prefix that covers the area it stands for.
		$this->assertStringContainsString(
			'data-nav-prefix="/cp/collection/"',
			$this->area($html, '/cp/collection/test-articles'),
		);
	}

	private function html(string $path): string
	{
		$response = $this->makeRequest('GET', $path);
		$this->assertResponseOk($response);

		return $this->getHtmlResponse($response);
	}

	/** The opening tag of the masthead area pointing at `$href`. */
	private function area(string $html, string $href): string
	{
		$found = preg_match(
			'/<a\s[^>]*class="area"[^>]*href="' . preg_quote($href, '/') . '"[^>]*>/',
			$html,
			$matches,
		);
		$this->assertSame(1, $found, "No masthead area for {$href}");

		return $matches[0];
	}
}
