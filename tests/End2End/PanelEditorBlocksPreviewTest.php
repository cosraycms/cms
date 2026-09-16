<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Cosray\Block as Builtin;
use Cosray\Bootstrap;
use Cosray\Config;
use Cosray\Field\Blocks;
use Cosray\Field\Textarea;
use Cosray\Tests\End2EndTestCase;
use Cosray\Tests\Fixtures\Collection\TestArticlesCollection;
use Cosray\Tests\Fixtures\Collection\TestBlocksCollection;
use Cosray\Tests\Fixtures\Node\TestNodeWithBlocks;

/**
 * The layout preview of a blocks field: the field rendered through the
 * site's render path from the submitted form, without saving.
 */
final class PanelEditorBlocksPreviewTest extends End2EndTestCase
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
		$plugin->collection(TestBlocksCollection::class);
		$plugin->node(TestNodeWithBlocks::class);

		return $plugin;
	}

	public function testRendersTheSubmittedFormOnTheReferenceSheetWithoutSaving(): void
	{
		$this->createBlocksNode('preview-blocks', 'test-media-document', [
			'contentBlocks' => [
				'type' => Blocks::class,
				'value' => [
					'en' => [$this->textBlock('block-a', 'Stored text', [
						'colspan' => 6,
						'rowspan' => 1,
						'indent' => 2,
					])],
				],
				'meta' => ['gap' => ['zxx' => 'l']],
			],
		]);

		$response = $this->makeRequest(
			'POST',
			'/cp/collection/test-articles/preview-blocks/blocks/contentBlocks',
			[
				'body' => [
					'content' => [
						'contentBlocks' => [
							'value' => [
								'en' => [
									[
										'uid' => '',
										'type' => Builtin\Heading::class,
										'layout' => ['colspan' => '12', 'rowspan' => '1', 'indent' => '0'],
										'fields' => [
											'text' => ['value' => ['zxx' => 'Fresh heading']],
											'level' => ['value' => ['zxx' => '3']],
										],
									],
									[
										'uid' => 'block-a',
										'type' => Builtin\Text::class,
										// Out of range: clamped into the grid, as the save would store it.
										'layout' => ['colspan' => '14', 'rowspan' => '1', 'indent' => '2'],
										'fields' => ['text' => ['value' => ['zxx' => 'Edited text']]],
									],
								],
							],
						],
					],
				],
			],
		);

		$this->assertResponseOk($response);
		$this->assertStringStartsWith('text/html', $response->getHeaderLine('Content-Type'));
		$html = $this->getHtmlResponse($response);

		$this->assertStringStartsWith('<!doctype html>', $html);
		$this->assertStringContainsString('<html lang="en">', $html);
		// The reference stylesheet is inlined, so the frame needs no request.
		$this->assertStringContainsString('@layer cms.blocks', $html);
		$this->assertHtmlNodeExists(
			'//div[contains(@class, "cms-blocks")][@data-columns="12"][@data-gap="l"]',
			$html,
		);
		$this->assertHtmlNodeExists('//div[@data-type="heading"]/h3[text()="Fresh heading"]', $html);
		$this->assertHtmlNodeExists(
			'//div[@data-type="text"][@data-colspan="12"][@data-indent="0"][contains(., "Edited text")]',
			$html,
		);
		$this->assertStringNotContainsString('Stored text', $html);

		// Nothing was written: neither the node nor a working copy.
		$stored = $this->nodeContent('preview-blocks')['contentBlocks']['value']['en'];
		$this->assertCount(1, $stored);
		$this->assertSame('Stored text', $stored[0]['fields']['text']['value']['zxx']);
		$drafts = $this->db()->execute(
			'SELECT count(*) AS count FROM cms.drafts d JOIN cms.nodes n ON n.node = d.node WHERE n.uid = :uid',
			['uid' => 'preview-blocks'],
		)->one();
		$this->assertSame(0, (int) $drafts['count']);
	}

	public function testRendersTheRequestedContentLanguageOfAnAsymmetricField(): void
	{
		$this->createBlocksNode('preview-locales', 'test-media-document', [
			'contentBlocks' => [
				'type' => Blocks::class,
				'value' => [
					'en' => [$this->textBlock('block-en', 'English text', [
						'colspan' => 12,
						'rowspan' => 1,
						'indent' => 0,
					])],
					'de' => [$this->textBlock('block-de', 'Deutscher Text', [
						'colspan' => 12,
						'rowspan' => 1,
						'indent' => 0,
					])],
				],
			],
		]);

		$german = $this->getHtmlResponse($this->makeRequest(
			'POST',
			'/cp/collection/test-articles/preview-locales/blocks/contentBlocks',
			['query' => ['locale' => 'de'], 'body' => ['content' => []]],
		));
		$this->assertStringContainsString('<html lang="de">', $german);
		$this->assertStringContainsString('Deutscher Text', $german);
		$this->assertStringNotContainsString('English text', $german);

		// An unknown locale falls back to the default one.
		$fallback = $this->getHtmlResponse($this->makeRequest(
			'POST',
			'/cp/collection/test-articles/preview-locales/blocks/contentBlocks',
			['query' => ['locale' => 'xx'], 'body' => ['content' => []]],
		));
		$this->assertStringContainsString('<html lang="en">', $fallback);
		$this->assertStringContainsString('English text', $fallback);
	}

	public function testRefusesUnknownNodesAndFieldsThatAreNotBlocks(): void
	{
		$this->createBlocksNode('preview-refused', 'test-media-document', [
			'contentBlocks' => ['type' => Blocks::class, 'value' => ['en' => []]],
		]);

		$this->assertResponseStatus(404, $this->makeRequest(
			'POST',
			'/cp/collection/test-articles/preview-refused/blocks/nope',
			['body' => ['content' => []]],
		));
		$this->assertResponseStatus(404, $this->makeRequest(
			'POST',
			'/cp/collection/test-articles/preview-refused/blocks/title',
			['body' => ['content' => []]],
		));
		$this->assertResponseStatus(404, $this->makeRequest(
			'POST',
			'/cp/collection/test-articles/no-such-node/blocks/contentBlocks',
			['body' => ['content' => []]],
		));
	}

	public function testPreviewsANewNodeFromItsBlueprint(): void
	{
		$response = $this->makeRequest(
			'POST',
			'/cp/collection/test-blocks/create/test-node-with-blocks/blocks/blocks',
			[
				'body' => [
					'content' => [
						'blocks' => [
							'value' => [
								'zxx' => [
									[
										'uid' => '',
										'type' => Builtin\Heading::class,
										'layout' => ['colspan' => '1', 'rowspan' => '1', 'indent' => '0'],
										'fields' => [
											'text' => ['value' => [
												'en' => 'Blueprint heading',
												'de' => 'Entwurfsüberschrift',
											]],
											'level' => ['value' => ['zxx' => '2']],
										],
									],
								],
							],
						],
					],
				],
			],
		);

		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);
		$this->assertHtmlNodeExists(
			'//div[contains(@class, "cms-blocks")][@data-columns="1"]/div[@data-type="heading"]/h2[text()="Blueprint heading"]',
			$html,
		);
		$this->assertStringNotContainsString('Entwurfsüberschrift', $html);

		$this->assertResponseStatus(404, $this->makeRequest(
			'POST',
			'/cp/collection/test-blocks/create/test-node-with-blocks/blocks/title',
			['body' => ['content' => []]],
		));
	}

	private function createBlocksNode(string $uid, string $handle, array $content): void
	{
		$type = $this->db()->execute(
			'SELECT type FROM cms.types WHERE handle = :handle',
			['handle' => $handle],
		)->first();
		$this->createTestNode([
			'uid' => $uid,
			'type' => $type ? (int) $type['type'] : $this->createTestType($handle),
			'published' => true,
			'content' => json_encode($content),
		]);
	}

	private function textBlock(string $uid, string $text, array $layout): array
	{
		return [
			'uid' => $uid,
			'type' => Builtin\Text::class,
			'layout' => $layout,
			'fields' => ['text' => ['type' => Textarea::class, 'value' => ['zxx' => $text]]],
		];
	}

	private function nodeContent(string $uid): array
	{
		$row = $this->db()->execute(
			'SELECT content FROM cms.nodes WHERE uid = :uid',
			['uid' => $uid],
		)->one();
		$this->assertNotEmpty($row);

		return json_decode((string) $row['content'], true);
	}
}
