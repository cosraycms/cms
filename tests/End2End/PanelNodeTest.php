<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Cosray\Field\Text;
use Cosray\Tests\End2EndTestCase;

final class PanelNodeTest extends End2EndTestCase
{
	private ?int $homeTypeId = null;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixtures('basic-types');
		$this->authenticateAs('editor');
	}

	public function testPanelNodeEditRouteRendersFieldForm(): void
	{
		$this->createPage('panel-node-edit', 'Unsafe <Title>');

		$response = $this->makeRequest('GET', '/cp/node/panel-node-edit');

		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);
		$this->assertStringContainsString('class="node-page"', $html);
		$this->assertStringContainsString('action="/cp/node/panel-node-edit"', $html);
		$this->assertStringContainsString('name="content[title][type]"', $html);
		$this->assertStringContainsString('name="content[title][value][en]"', $html);
		$this->assertStringContainsString('Unsafe &lt;Title&gt;', $html);
		$this->assertStringNotContainsString('Unsafe <Title>', $html);
	}

	public function testPanelNodeEditRouteSavesNativeFormValues(): void
	{
		$this->createPage('panel-node-save', 'Before');

		$response = $this->makeRequest('POST', '/cp/node/panel-node-save', [
			'form' => [
				'uid' => 'panel-node-save',
				'published' => '1',
				'hidden' => '0',
				'locked' => '0',
				'content' => [
					'title' => [
						'type' => Text::class,
						'value' => [
							'en' => 'After',
							'de' => '',
						],
					],
				],
			],
		]);

		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);
		$this->assertStringContainsString('Saved.', $html);
		$this->assertStringContainsString('After', $html);
	}

	public function testPanelNodeEditRouteReturnsNotFoundForUnknownNode(): void
	{
		$response = $this->makeRequest('GET', '/cp/node/does-not-exist');

		$this->assertResponseStatus(404, $response);
	}

	private function createPage(string $uid, string $title): void
	{
		$this->createTestNode([
			'uid' => $uid,
			'type' => $this->homeTypeId(),
			'published' => true,
			'content' => [
				'title' => [
					'type' => 'text',
					'value' => ['en' => $title],
				],
			],
		]);
	}

	private function homeTypeId(): int
	{
		if ($this->homeTypeId !== null) {
			return $this->homeTypeId;
		}

		$type = $this->db()->execute(
			"SELECT type FROM cms.types WHERE handle = 'test-home'",
		)->one();
		$this->assertNotEmpty($type);
		$this->homeTypeId = (int) $type['type'];

		return $this->homeTypeId;
	}
}
