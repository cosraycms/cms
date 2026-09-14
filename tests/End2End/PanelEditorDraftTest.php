<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Cosray\Bootstrap;
use Cosray\Config;
use Cosray\Field\Text;
use Cosray\Tests\End2EndTestCase;
use Cosray\Tests\Fixtures\Collection\TestArticlesCollection;

/**
 * The editor's save routing around working copies: which saves land in
 * the draft row, which write the live row, and what the save response
 * tells the editor afterwards.
 *
 * @internal
 *
 * @coversNothing
 */
final class PanelEditorDraftTest extends End2EndTestCase
{
	private int $pageTypeId;
	private int $articleTypeId;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixtures('basic-types');
		$this->authenticateAs('editor');
		$this->pageTypeId = $this->typeId('test-page');
		$this->articleTypeId = $this->typeId('test-article');
	}

	protected function createBootstrap(Config $config): Bootstrap
	{
		$plugin = parent::createBootstrap($config);
		$plugin->section('Inhalt')->collection(TestArticlesCollection::class);

		return $plugin;
	}

	public function testPlainSaveOfAPublishedNodeLandsInTheWorkingCopy(): void
	{
		$this->createPage('draft-plain', 'Live', published: true);

		$response = $this->save('draft-plain', ['content' => ['title' => ['value' => ['en' => 'Changed']]]]);

		$this->assertResponseOk($response);
		$this->assertSame('Live', $this->liveTitle('draft-plain'));
		$this->assertSame('Changed', $this->draftTitle('draft-plain'));

		$html = $this->getHtmlResponse($response);
		$this->assertHtmlNodeExists('//*[@id="editor-changes" and @hx-swap-oob="true" and not(@hidden)]', $html);
		$this->assertHtmlNodeExists(
			'//*[@id="editor-save-options" and @hx-swap-oob="true"]//button[@name="publish"][contains(., "Publish changes")]',
			$html,
		);
		$this->assertHtmlNodeExists(
			'//*[@id="editor-save-options"]//button[@form="node-editor-discard"]',
			$html,
		);
		$this->assertHtmlNodeExists('//*[@id="editor-changes-note" and @hx-swap-oob="true" and not(@hidden)]', $html);
		$this->assertHtmlNodeExists(
			'//*[@id="editor-changes-note"]//button[@form="node-editor-discard"]',
			$html,
		);
		$this->assertHtmlNodeExists(
			'//*[@id="editor-changes-note"]//button[@form="node-editor-form"][@name="publish"][@data-editor-submit]',
			$html,
		);
	}

	public function testTheEditorShowsTheWorkingCopy(): void
	{
		$this->createPage('draft-shown', 'Live', published: true);
		$this->save('draft-shown', ['content' => ['title' => ['value' => ['en' => 'Working']]]]);

		$html = (string) $this->makeRequest('GET', '/cp/collection/test-articles/draft-shown')->getBody();

		$this->assertHtmlNodeExists('//input[@name="content[title][value][en]"][@value="Working"]', $html);
		$this->assertHtmlNodeExists('//*[@id="editor-changes" and not(@hidden)]', $html);
		$this->assertHtmlNodeExists('//form[@id="node-editor-discard"][@data-dirty-bypass]', $html);
	}

	public function testASecondSavePatchesTheWorkingCopyNotTheLiveRow(): void
	{
		$this->createPage('draft-merge', 'Live', published: true);
		$this->save('draft-merge', ['content' => ['title' => ['value' => ['en' => 'First']]]]);

		$this->save('draft-merge', ['content' => ['title' => ['value' => ['de' => 'Zweiter']]]]);

		$draft = $this->draftContent('draft-merge');
		$this->assertSame('First', $draft['title']['value']['en']);
		$this->assertSame('Zweiter', $draft['title']['value']['de']);
		$this->assertSame('Live', $this->liveTitle('draft-merge'));
	}

	public function testPublishingWritesTheWorkingCopyLive(): void
	{
		$this->createPage('draft-publish', 'Live', published: true);
		$this->save('draft-publish', ['content' => ['title' => ['value' => ['en' => 'Released']]]]);

		$response = $this->save('draft-publish', ['publish' => '1']);

		$this->assertResponseOk($response);
		$this->assertSame('Released', $this->liveTitle('draft-publish'));
		$this->assertNull($this->draftRow('draft-publish'));
		$html = $this->getHtmlResponse($response);
		$this->assertHtmlNodeExists('//*[@id="editor-changes" and @hidden]', $html);
		$this->assertHtmlNodeExists(
			'//*[@id="editor-save-options"]//button[@name="publish"][contains(., "Save and publish")]',
			$html,
		);
		$this->assertHtmlNodeMissing('//*[@id="editor-save-options"]//button[@form="node-editor-discard"]', $html);
	}

	public function testTurningTheSwitchOffFoldsTheWorkingCopyIn(): void
	{
		$this->createPage('draft-fold', 'Live', published: true);
		$this->save('draft-fold', ['content' => ['title' => ['value' => ['en' => 'Pending']]]]);

		$this->save('draft-fold', ['published' => '', 'content' => ['title' => ['value' => ['de' => 'Offline']]]]);

		$row = $this->nodeRow('draft-fold');
		$this->assertFalse((bool) $row['published']);
		$content = json_decode((string) $row['content'], true);
		$this->assertSame('Pending', $content['title']['value']['en']);
		$this->assertSame('Offline', $content['title']['value']['de']);
		$this->assertNull($this->draftRow('draft-fold'));
	}

	public function testAnUnpublishedNodeIsEditedInPlace(): void
	{
		$this->createPage('draft-unpublished', 'Live', published: false);

		$this->save('draft-unpublished', ['content' => ['title' => ['value' => ['en' => 'Direct']]]]);

		$this->assertSame('Direct', $this->liveTitle('draft-unpublished'));
		$this->assertNull($this->draftRow('draft-unpublished'));
	}

	public function testANodeWithoutATemplateIsEditedInPlace(): void
	{
		$this->createTestNode([
			'uid' => 'draft-article',
			'type' => $this->articleTypeId,
			'published' => true,
			'content' => ['title' => ['type' => Text::class, 'value' => ['en' => 'Live']]],
		]);

		$this->save('draft-article', ['content' => ['title' => ['value' => ['en' => 'Direct']]]]);

		$this->assertSame('Direct', $this->liveTitle('draft-article'));
		$this->assertNull($this->draftRow('draft-article'));
	}

	public function testHiddenAppliesLiveWhileTheContentWaits(): void
	{
		$this->createPage('draft-hidden', 'Live', published: true);

		$this->save('draft-hidden', ['hidden' => '1', 'content' => ['title' => ['value' => ['en' => 'Pending']]]]);

		$row = $this->nodeRow('draft-hidden');
		$this->assertTrue((bool) $row['hidden']);
		$this->assertSame('Live', $this->liveTitle('draft-hidden'));
		$this->assertSame('Pending', $this->draftTitle('draft-hidden'));
	}

	public function testDiscardDropsTheWorkingCopyAndReturnsToTheEditor(): void
	{
		$this->createPage('draft-discard', 'Live', published: true);
		$this->save('draft-discard', ['content' => ['title' => ['value' => ['en' => 'Pending']]]]);

		$response = $this->makeRequest('POST', '/cp/collection/test-articles/draft-discard/discard', [
			'headers' => ['HX-Request' => 'true'],
		]);

		$this->assertResponseStatus(303, $response);
		$this->assertStringEndsWith('/cp/collection/test-articles/draft-discard', $response->getHeaderLine('Location'));
		$this->assertNull($this->draftRow('draft-discard'));
		$this->assertSame('Live', $this->liveTitle('draft-discard'));
	}

	public function testPreviewPointsAtTheWorkingCopy(): void
	{
		$this->createPage('draft-preview', 'Live', published: true);

		$response = $this->save('draft-preview', [
			'preview' => '1',
			'content' => ['title' => ['value' => ['en' => 'Pending']]],
		]);

		$this->assertHtmlNodeExists(
			'//*[@id="editor-preview"]//iframe[@src="/preview/draft-preview"]',
			$this->getHtmlResponse($response),
		);
		$this->assertSame('Pending', $this->draftTitle('draft-preview'));
	}

	private function save(string $uid, array $body): object
	{
		return $this->makeRequest('POST', '/cp/collection/test-articles/' . $uid, [
			'headers' => ['HX-Request' => 'true'],
			'body' => $body + ['_complete' => '1'],
		]);
	}

	private function createPage(string $uid, string $title, bool $published): void
	{
		$this->createTestNode([
			'uid' => $uid,
			'type' => $this->pageTypeId,
			'published' => $published,
			'content' => ['title' => ['type' => Text::class, 'value' => ['en' => $title]]],
		]);
	}

	private function typeId(string $handle): int
	{
		return (int) $this->db()->execute('SELECT type FROM cms.types WHERE handle = :handle', [
			'handle' => $handle,
		])->one()['type'];
	}

	/** @return array<string, mixed> */
	private function nodeRow(string $uid): array
	{
		$row = $this->db()->execute('SELECT * FROM cms.nodes WHERE uid = :uid', ['uid' => $uid])->first();
		$this->assertNotNull($row);

		return $row;
	}

	/** @return ?array<string, mixed> */
	private function draftRow(string $uid): ?array
	{
		return (
			$this->db()->execute(
				'SELECT d.* FROM cms.drafts d JOIN cms.nodes n ON n.node = d.node WHERE n.uid = :uid',
				['uid' => $uid],
			)->first() ?: null
		);
	}

	private function liveTitle(string $uid): string
	{
		return json_decode((string) $this->nodeRow($uid)['content'], true)['title']['value']['en'];
	}

	/** @return array<string, mixed> */
	private function draftContent(string $uid): array
	{
		$row = $this->draftRow($uid);
		$this->assertNotNull($row);

		return json_decode((string) $row['content'], true);
	}

	private function draftTitle(string $uid): string
	{
		return $this->draftContent($uid)['title']['value']['en'];
	}
}
