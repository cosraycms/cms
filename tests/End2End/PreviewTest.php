<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Cosray\Field\Text;
use Cosray\Tests\End2EndTestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class PreviewTest extends End2EndTestCase
{
	private int $typeId;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixtures('basic-types');
		$this->typeId = $this->typeId('test-page');
	}

	public function testPreviewRendersTheWorkingCopyOfAPublishedNode(): void
	{
		$nodeId = $this->createPage('preview-working', 'Live title', published: true);
		$this->db()->execute(
			'INSERT INTO cms.drafts (node, editor, content) VALUES (:node, 1, :content::jsonb)',
			[
				'node' => $nodeId,
				'content' => json_encode(['title' => ['type' => Text::class, 'value' => ['en' => 'Working title']]]),
			],
		)->run();
		$this->authenticateAs('editor');

		$response = $this->makeRequest('GET', '/preview/preview-working');

		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);
		$this->assertStringContainsString('<h1>Working title</h1>', $html);
		$this->assertStringNotContainsString('Live title', $html);
	}

	public function testPreviewRendersAnUnpublishedNode(): void
	{
		$this->createPage('preview-unpublished', 'Not yet live', published: false);
		$this->authenticateAs('editor');

		$response = $this->makeRequest('GET', '/preview/preview-unpublished');

		$this->assertResponseOk($response);
		$this->assertStringContainsString('<h1>Not yet live</h1>', $this->getHtmlResponse($response));
	}

	public function testPreviewIsNotFoundForNodesWithoutATemplate(): void
	{
		$this->createTestNode([
			'uid' => 'preview-plain',
			'type' => $this->typeId('test-article'),
			'published' => true,
		]);
		$this->authenticateAs('editor');

		$this->assertResponseStatus(404, $this->makeRequest('GET', '/preview/preview-plain'));
	}

	public function testPreviewNeedsThePanelPermission(): void
	{
		$this->createPage('preview-guarded', 'Guarded', published: true);

		$this->assertResponseStatus(401, $this->makeRequest('GET', '/preview/preview-guarded'));
	}

	private function typeId(string $handle): int
	{
		return (int) $this->db()->execute('SELECT type FROM cms.types WHERE handle = :handle', [
			'handle' => $handle,
		])->one()['type'];
	}

	private function createPage(string $uid, string $title, bool $published): int
	{
		return $this->createTestNode([
			'uid' => $uid,
			'type' => $this->typeId,
			'published' => $published,
			'content' => ['title' => ['type' => Text::class, 'value' => ['en' => $title]]],
		]);
	}
}
