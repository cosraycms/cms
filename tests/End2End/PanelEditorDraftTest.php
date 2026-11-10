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
		$this->assertHtmlNodeExists($this->stage('draft', 'present'), $html);
		$this->assertHtmlNodeExists(
			'//*[@id="editor-status-panel" and @hx-swap-oob="true"]//button[@form="node-editor-discard"]',
			$html,
		);
		$this->assertHtmlNodeExists('//*[@data-toast="success"][contains(., "Draft saved")]', $html);
	}

	public function testASaveWithoutChangesLeavesNoWorkingCopy(): void
	{
		$this->createPage('draft-unchanged', 'Live', published: true);

		$response = $this->save('draft-unchanged', [
			'content' => ['title' => ['value' => ['en' => 'Live', 'de' => '']]],
		]);

		$this->assertResponseOk($response);
		$this->assertNull($this->draftRow('draft-unchanged'));
		$this->assertHtmlNodeExists($this->stage('draft', 'none'), $this->getHtmlResponse($response));
	}

	public function testSavingTheLiveStateAgainDropsTheWorkingCopy(): void
	{
		$this->createPage('draft-reverted', 'Live', published: true);
		$this->save('draft-reverted', ['content' => ['title' => ['value' => ['en' => 'Changed']]]]);

		$response = $this->save('draft-reverted', ['content' => ['title' => ['value' => ['en' => 'Live']]]]);

		$this->assertResponseOk($response);
		$this->assertNull($this->draftRow('draft-reverted'));
		$this->assertSame('Live', $this->liveTitle('draft-reverted'));
		$html = $this->getHtmlResponse($response);
		$this->assertHtmlNodeExists($this->stage('draft', 'none'), $html);
		$this->assertHtmlNodeMissing('//*[@id="editor-status-panel"]//button[@form="node-editor-discard"]', $html);
	}

	public function testTheEditorShowsTheWorkingCopy(): void
	{
		$this->createPage('draft-shown', 'Live', published: true);
		$this->save('draft-shown', ['content' => ['title' => ['value' => ['en' => 'Working']]]]);

		$html = (string) $this->makeRequest('GET', '/panel/node/draft-shown')->getBody();

		$this->assertHtmlNodeExists('//input[@name="content[title][value][en]"][@value="Working"]', $html);
		$this->assertHtmlNodeExists($this->stage('draft', 'present'), $html);
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
		$this->assertHtmlNodeExists($this->stage('draft', 'none'), $html);
		$this->assertHtmlNodeExists($this->stage('website', 'online'), $html);
		$this->assertHtmlNodeExists('//*[@data-toast="success"][contains(., "Published")]', $html);
	}

	public function testTheOnlineSwitchActsAtOnceAndKeepsTheWorkingCopy(): void
	{
		$this->createPage('draft-offline', 'Live', published: true);
		$this->save('draft-offline', ['content' => ['title' => ['value' => ['en' => 'Pending']]]]);

		$response = $this->visibility('draft-offline', ['listed' => '1']);

		$this->assertResponseOk($response);
		$this->assertStringContainsString('data-toast="success"', $this->getHtmlResponse($response));
		$this->assertFalse((bool) $this->nodeRow('draft-offline')['published']);
		$this->assertSame('Live', $this->liveTitle('draft-offline'));
		$this->assertSame('Pending', $this->draftTitle('draft-offline'));

		// Back online, visitors get the released version while the draft waits.
		$response = $this->visibility('draft-offline', ['online' => '1', 'listed' => '1']);

		$this->assertStringContainsString('data-toast="info"', $this->getHtmlResponse($response));
		$this->assertTrue((bool) $this->nodeRow('draft-offline')['published']);
		$this->assertSame('Pending', $this->draftTitle('draft-offline'));
	}

	public function testANeverPublishedNodeCannotBeSwitchedOnline(): void
	{
		$this->createPage('draft-new', 'New', published: false);

		$response = $this->visibility('draft-new', ['online' => '1', 'listed' => '1']);

		$this->assertStringContainsString('data-toast="error"', $this->getHtmlResponse($response));
		$this->assertFalse((bool) $this->nodeRow('draft-new')['published']);
		$this->assertNull($this->nodeRow('draft-new')['released']);
	}

	public function testAReservedPathNeverReachesTheWorkingCopy(): void
	{
		$this->createPage('draft-reserved', 'Live', published: true);

		$response = $this->save('draft-reserved', ['paths' => ['en' => '/panel/about']]);

		$this->assertResponseOk($response);
		$this->assertStringContainsString(
			'The URL path /panel/about is reserved by the system; choose another one',
			$this->getHtmlResponse($response),
		);
		$this->assertNull($this->draftRow('draft-reserved'));
	}

	public function testAReservedPathIsRefusedWhenSavedInPlace(): void
	{
		$this->createPage('draft-reserved-live', 'Offline', published: false);

		$response = $this->save('draft-reserved-live', ['published' => '', 'paths' => ['en' => '/assets']]);

		$this->assertStringContainsString(
			'The URL path /assets is reserved by the system; choose another one',
			$this->getHtmlResponse($response),
		);
		$this->assertNull(
			$this->db()->execute(
				"SELECT path FROM cms.url_paths WHERE path = '/assets'",
			)->first(),
		);
	}

	public function testAnUnpublishedNodeIsEditedInPlace(): void
	{
		$this->createPage('draft-unpublished', 'Live', published: false);

		$this->save('draft-unpublished', ['content' => ['title' => ['value' => ['en' => 'Direct']]]]);

		$this->assertSame('Direct', $this->liveTitle('draft-unpublished'));
		$this->assertNull($this->draftRow('draft-unpublished'));
	}

	public function testANodeWithoutATemplateHoldsAWorkingCopyToo(): void
	{
		$this->createTestNode([
			'uid' => 'draft-article',
			'type' => $this->articleTypeId,
			'published' => true,
			'content' => ['title' => ['type' => Text::class, 'value' => ['en' => 'Live']]],
		]);

		$this->save('draft-article', ['content' => ['title' => ['value' => ['en' => 'Pending']]]]);

		$this->assertSame('Live', $this->liveTitle('draft-article'));
		$this->assertSame('Pending', $this->draftTitle('draft-article'));
	}

	public function testANeverPublishedNodeIsEditedInPlace(): void
	{
		$this->createPage('draft-unreleased', 'First', published: false);

		$this->save('draft-unreleased', ['content' => ['title' => ['value' => ['en' => 'Second']]]]);

		$this->assertSame('Second', $this->liveTitle('draft-unreleased'));
		$this->assertNull($this->draftRow('draft-unreleased'));
		$this->assertFalse((bool) $this->nodeRow('draft-unreleased')['published']);
	}

	public function testPublishingSwitchesOnlineAndReleases(): void
	{
		$this->createPage('draft-release', 'First', published: false);

		$this->save('draft-release', ['publish' => '1', 'content' => ['title' => ['value' => ['en' => 'Out']]]]);

		$row = $this->nodeRow('draft-release');
		$this->assertTrue((bool) $row['published']);
		$this->assertNotNull($row['released']);
		$this->assertSame('Out', $this->liveTitle('draft-release'));
	}

	public function testARecordIsSavedInPlaceAndHasNoVisibility(): void
	{
		$this->createTestNode([
			'uid' => 'draft-record',
			'type' => $this->createTestType('test-submission'),
			'published' => true,
			'content' => ['title' => ['type' => Text::class, 'value' => ['zxx' => 'Entry']]],
		]);

		$this->save('draft-record', ['content' => ['title' => ['value' => ['zxx' => 'Edited']]]]);

		$this->assertNull($this->draftRow('draft-record'));
		$this->assertStringContainsString('Edited', (string) $this->nodeRow('draft-record')['content']);
		$this->assertResponseStatus(400, $this->visibility('draft-record', ['listed' => '1']));
	}

	public function testTheListingSwitchActsAtOnceWhileTheContentWaits(): void
	{
		$this->createPage('draft-hidden', 'Live', published: true);
		$this->save('draft-hidden', ['content' => ['title' => ['value' => ['en' => 'Pending']]]]);

		$this->visibility('draft-hidden', ['online' => '1']);

		$row = $this->nodeRow('draft-hidden');
		$this->assertTrue((bool) $row['hidden']);
		$this->assertTrue((bool) $row['published']);
		$this->assertSame('Live', $this->liveTitle('draft-hidden'));
		$this->assertSame('Pending', $this->draftTitle('draft-hidden'));
	}

	public function testDiscardDropsTheWorkingCopyAndReturnsToTheEditor(): void
	{
		$this->createPage('draft-discard', 'Live', published: true);
		$this->save('draft-discard', ['content' => ['title' => ['value' => ['en' => 'Pending']]]]);

		$response = $this->makeRequest('POST', '/panel/node/draft-discard/discard', [
			'headers' => ['HX-Request' => 'true'],
			'query' => ['from' => 'collection:test-articles', 'list' => ['q' => 'Live', 'offset' => 50]],
		]);

		$this->assertResponseOk($response);
		$location = json_decode($response->getHeaderLine('HX-Location'), true);
		$this->assertSame(
			'/panel/node/draft-discard?from=collection%3Atest-articles&list%5Bq%5D=Live&list%5Boffset%5D=50',
			$location['path'] ?? null,
		);
		$this->assertSame('#main', $location['target'] ?? null);
		$this->assertNull($this->draftRow('draft-discard'));
		$this->assertSame('Live', $this->liveTitle('draft-discard'));

		$plain = $this->makeRequest('POST', '/panel/node/draft-discard/discard');

		$this->assertResponseStatus(303, $plain);
		$this->assertStringEndsWith('/panel/node/draft-discard', $plain->getHeaderLine('Location'));
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
		return $this->makeRequest('POST', '/panel/node/' . $uid, [
			'headers' => ['HX-Request' => 'true'],
			'body' => $body + ['_complete' => '1'],
		]);
	}

	public function testARecordShowsTheStoredStateAndOnlySaves(): void
	{
		$this->createTestNode([
			'uid' => 'draft-record-head',
			'type' => $this->createTestType('test-submission'),
			'content' => ['title' => ['type' => Text::class, 'value' => ['zxx' => 'Entry']]],
		]);

		$html = (string) $this->makeRequest('GET', '/panel/node/draft-record-head')->getBody();

		$this->assertHtmlNodeExists($this->stage('saved', 'saved'), $html);
		$this->assertHtmlNodeMissing("//*[@id='editor-status-dots']/*[@data-stage='website']", $html);
		$this->assertHtmlNodeMissing('//button[@name="publish"]', $html);
		$this->assertHtmlNodeMissing('//form[@id="node-editor-visibility"]', $html);
	}

	public function testDuplicatingOpensTheCopy(): void
	{
		$this->createPage('draft-original', 'Original', published: true);

		$response = $this->makeRequest('POST', '/panel/node/draft-original/duplicate', [
			'headers' => ['HX-Request' => 'true'],
		]);

		$location = json_decode($response->getHeaderLine('HX-Location'), true);
		$this->assertIsArray($location);
		$this->assertStringStartsWith('/panel/node/', $location['path']);
		$this->assertNotSame('/panel/node/draft-original', $location['path']);
		$copy = $this->nodeRow(substr($location['path'], strlen('/panel/node/')));
		$this->assertNull($copy['released']);
	}

	/** The head's dot of a stage in a state. */
	private function stage(string $stage, string $state): string
	{
		return "//*[@id='editor-status-dots']/*[@data-stage='{$stage}' and @data-state='{$state}']";
	}

	/** @param array<string, string> $states the switches' form, unchecked ones left out */
	private function visibility(string $uid, array $states): object
	{
		return $this->makeRequest('POST', '/panel/node/' . $uid . '/visibility', [
			'headers' => ['HX-Request' => 'true'],
			'body' => $states,
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
