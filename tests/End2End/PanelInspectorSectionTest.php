<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Cosray\Bootstrap;
use Cosray\Config;
use Cosray\Field\Text;
use Cosray\Tests\End2EndTestCase;
use Cosray\Tests\Fixtures\Plugin\TestPlugin;

/**
 * Sections a plugin adds to the editor's inspector: where they render,
 * how their controls post and how they follow the node's state.
 *
 * @internal
 *
 * @coversNothing
 */
final class PanelInspectorSectionTest extends End2EndTestCase
{
	private const string SECTION = '//section[@id="cms-inspector-section-test-plugin"]';

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixtures('basic-types');
		$this->authenticateAs('editor');
	}

	protected function createBootstrap(Config $config): Bootstrap
	{
		$bootstrap = parent::createBootstrap($config);
		$bootstrap->plugin(TestPlugin::class);

		return $bootstrap;
	}

	public function testASectionRendersInTheStatusTabWithAFormOfItsOwn(): void
	{
		$this->createNode('section-page', 'test-page');

		$html = $this->getHtmlResponse($this->makeRequest('GET', '/panel/node/section-page'));

		$this->assertHtmlNodeExists(
			'//*[@id="cms-inspector-panel-status"]'
				. self::SECTION
				. '[@data-editor-state]//h2[normalize-space(.)="Test notes"]',
			$html,
		);
		$this->assertHtmlNodeExists(
			self::SECTION . '//input[@name="note" and @form="cms-inspector-form-test-plugin"]',
			$html,
		);
		$this->assertHtmlNodeExists(self::SECTION . '//*[@data-test-online="no"]', $html);
		// Forms cannot nest, so the section's form stands beside the editor's.
		$this->assertHtmlNodeExists(
			'//form[@id="cms-inspector-form-test-plugin" and @action="/panel/test-plugin/notes/section-page"'
				. ' and @method="post" and not(ancestor::form)]/input[@name="_token"]',
			$html,
		);
	}

	public function testATypeTheSectionLeavesOutShowsNone(): void
	{
		$this->createNode('section-article', 'test-article');

		$html = $this->getHtmlResponse($this->makeRequest('GET', '/panel/node/section-article'));

		$this->assertHtmlNodeMissing(self::SECTION, $html);
		$this->assertHtmlNodeMissing('//form[@id="cms-inspector-form-test-plugin"]', $html);
	}

	public function testASectionFollowsTheNodeAfterASwitch(): void
	{
		$this->createNode('section-switch', 'test-page');

		$response = $this->makeRequest('POST', '/panel/node/section-switch/visibility', [
			'headers' => ['HX-Request' => 'true'],
			'body' => ['published' => '1', 'listed' => '1'],
		]);

		$this->assertResponseOk($response);
		$this->assertHtmlNodeExists(
			self::SECTION . '[@hx-swap-oob="true"]//*[@data-test-online="yes"]',
			$this->getHtmlResponse($response),
		);
	}

	public function testASectionFollowsTheNodeAfterASave(): void
	{
		$this->createNode('section-save', 'test-page');

		$response = $this->makeRequest('POST', '/panel/node/section-save', [
			'headers' => ['HX-Request' => 'true'],
			'body' => ['content' => ['title' => ['value' => ['en' => 'Changed']]], '_complete' => '1'],
		]);

		$this->assertResponseOk($response);
		$this->assertHtmlNodeExists(self::SECTION . '[@hx-swap-oob="true"]', $this->getHtmlResponse($response));
	}

	public function testThePluginsOwnRouteDrawsItsSectionAgain(): void
	{
		$this->createNode('section-note', 'test-page');

		$response = $this->makeRequest('POST', '/panel/test-plugin/notes/section-note', [
			'headers' => ['HX-Request' => 'true'],
			'body' => ['note' => 'Remember the photos'],
		]);

		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);
		$this->assertHtmlNodeExists(
			self::SECTION . '[@hx-swap-oob="true"]//*[@data-test-note][normalize-space(.)="Remember the photos"]',
			$html,
		);
		$this->assertHtmlNodeExists('//*[@data-toast="success"][contains(., "Noted")]', $html);
	}

	private function createNode(string $uid, string $type): void
	{
		$this->createTestNode([
			'uid' => $uid,
			'type' => (int) $this->db()->execute('SELECT type FROM cms.types WHERE handle = :handle', [
				'handle' => $type,
			])->one()['type'],
			'published' => false,
			'content' => ['title' => ['type' => Text::class, 'value' => ['en' => 'Section']]],
		]);
	}
}
