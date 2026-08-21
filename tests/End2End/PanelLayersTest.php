<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Cosray\Bootstrap;
use Cosray\Config;
use Cosray\Tests\End2EndTestCase;
use Cosray\Tests\Fixtures\Collection\TestArticlesCollection;

/**
 * How much of the panel a response renders. htmx names the region it swaps and
 * the layer templates stop there; whatever stays in the page and carries an
 * active mark patches itself in out of band.
 *
 * @internal
 *
 * @coversNothing
 */
final class PanelLayersTest extends End2EndTestCase
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

	public function testAPlainRequestRendersTheWholeDocument(): void
	{
		$html = $this->layerHtml();

		$this->assertStringContainsString('<!DOCTYPE html>', $html);
		$this->assertStringContainsString('class="cms-masthead"', $html);
		$this->assertStringContainsString('id="frame"', $html);
		$this->assertStringContainsString('class="page cms-collection"', $html);
		$this->assertStringContainsString('id="verba-catalog"', $html);
	}

	public function testNavigatingInsideAnAreaRendersTheContentRegionAlone(): void
	{
		$html = $this->layerHtml([
			'HX-Request' => 'true',
			'HX-Boosted' => 'true',
			'HX-Target' => 'main#main',
		]);

		$this->assertStringContainsString('class="page cms-collection"', $html);
		$this->assertStringNotContainsString('<!DOCTYPE html>', $html);
		$this->assertStringNotContainsString('class="cms-masthead"', $html);
		$this->assertStringNotContainsString('class="cms-sidebar"', $html);
	}

	/**
	 * The rail stays in the page during such a swap, so the response carries
	 * the tree with the mark the new URL puts on it.
	 */
	public function testNavigatingInsideAnAreaPatchesTheRail(): void
	{
		$html = $this->layerHtml([
			'HX-Request' => 'true',
			'HX-Boosted' => 'true',
			'HX-Target' => 'main#main',
		]);

		$this->assertStringContainsString('id="collection-nav" hx-swap-oob="true"', $html);
		$this->assertMatchesRegularExpression(
			'/<a\s[^>]*href="\/cp\/collection\/test-articles"[^>]*aria-current="page"/',
			$html,
		);
	}

	public function testAnAreaSwitchRendersTheFrameAndPatchesTheMasthead(): void
	{
		$html = $this->layerHtml([
			'HX-Request' => 'true',
			'HX-Boosted' => 'true',
			'HX-Target' => 'div#frame',
		]);

		$this->assertStringContainsString('id="frame"', $html);
		$this->assertStringContainsString('class="cms-sidebar"', $html);
		$this->assertStringNotContainsString('<!DOCTYPE html>', $html);
		$this->assertStringNotContainsString('class="cms-masthead"', $html);

		// The masthead is not part of the swap, so its own mark comes along.
		$this->assertStringContainsString('id="area-nav"', $html);
		$this->assertStringContainsString('hx-swap-oob="true"', $html);
	}

	/**
	 * htmx restores history by swapping the body. Nothing in that response may
	 * re-run: the panel scripts are already loaded, and htmx executes the
	 * scripts it inserts.
	 *
	 * A restore carries this one header and none of the others, so it cannot be
	 * recognised by the target or the request type.
	 */
	public function testAHistoryRestoreRendersTheBodyWithoutTheScripts(): void
	{
		$html = $this->layerHtml(['HX-History-Restore-Request' => 'true']);

		$this->assertStringContainsString('class="cms-masthead"', $html);
		$this->assertStringContainsString('id="frame"', $html);
		$this->assertStringContainsString('class="page cms-collection"', $html);
		$this->assertStringNotContainsString('<!DOCTYPE html>', $html);
		$this->assertStringNotContainsString('<script src=', $html);

		// Element bundles read the catalog when they first run, which can be
		// after a restore replaced the body.
		$this->assertStringContainsString('id="verba-catalog"', $html);
	}

	public function testARequestAimedAtTheBodyRendersTheBody(): void
	{
		$html = $this->layerHtml([
			'HX-Request' => 'true',
			'HX-Request-Type' => 'full',
			'HX-Target' => 'body',
		]);

		$this->assertStringContainsString('class="cms-masthead"', $html);
		$this->assertStringNotContainsString('<!DOCTYPE html>', $html);
	}

	/** @param array<string, string> $headers */
	private function layerHtml(array $headers = []): string
	{
		$response = $this->makeRequest(
			'GET',
			'/cp/collection/test-articles',
			$headers === [] ? [] : ['headers' => $headers],
		);
		$this->assertResponseOk($response);

		return $this->getHtmlResponse($response);
	}
}
