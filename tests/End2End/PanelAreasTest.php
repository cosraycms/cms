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

	public function testOnlyAnAreaWithARailRendersOne(): void
	{
		$this->assertStringContainsString(
			'class="cms-sidebar"',
			$this->html('/cp/collection/test-articles'),
		);
		$this->assertStringNotContainsString('class="cms-sidebar"', $this->html('/cp'));
	}

	public function testTheMediaRailIsNotAnnouncedAsNavigation(): void
	{
		$html = $this->html('/cp/media');

		$this->assertHtmlNodeExists('//aside[@class="cms-sidebar"]//*[@data-media-rail]', $html);
		$this->assertHtmlNodeMissing('//aside[@class="cms-sidebar"]//nav', $html);
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

		$this->assertHtmlNodeCount(
			3,
			'//a[contains(concat(" ", normalize-space(@class), " "), " area ")]',
			$html,
		);
		$this->assertHtmlNodeExists(
			'//a[@href="/cp/collection/test-articles" and contains(concat(" ", normalize-space(@class), " "), " area ") and normalize-space(.)="Content"]',
			$html,
		);

		// Switching areas replaces everything below the masthead, so the whole
		// nav swaps the frame rather than the content region.
		$this->assertStringContainsString('hx-target:inherited="#frame"', $html);
	}

	/** The account menu's language choices have to mark the language the panel is showing. */
	public function testTheLanguageSwitcherMarksTheActiveLocale(): void
	{
		$german = $this->html('/cp', 'de');
		$this->assertHtmlNodeExists($this->localeChoice('de', 'true'), $german);
		$this->assertHtmlNodeExists($this->localeChoice('en', 'false'), $german);
		$this->assertStringContainsString('<html lang="de">', $german);

		$english = $this->html('/cp', 'en');
		$this->assertHtmlNodeExists($this->localeChoice('en', 'true'), $english);
		$this->assertHtmlNodeExists($this->localeChoice('de', 'false'), $english);
		$this->assertStringContainsString('<html lang="en">', $english);
	}

	private function localeChoice(string $locale, string $checked): string
	{
		return "//button[@name=\"locale\" and @value=\"{$locale}\" and @role=\"menuitemradio\" and @aria-checked=\"{$checked}\"]";
	}

	private function html(string $path, ?string $language = null): string
	{
		$response = $this->makeRequest(
			'GET',
			$path,
			$language === null ? [] : ['headers' => ['Accept-Language' => $language]],
		);
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
