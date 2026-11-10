<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Cosray\Field\Text;
use Cosray\Tests\End2EndTestCase;

/**
 * The editor's autosave: kept beside the node, back in the editor when it
 * opens again, and gone once the edits are saved or discarded.
 *
 * @internal
 *
 * @coversNothing
 */
final class PanelEditorAutosaveTest extends End2EndTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixtures('basic-types');
		$this->authenticateAs('editor');
	}

	public function testAnAutosaveKeepsTheEditsWithoutTouchingTheNode(): void
	{
		$this->createPage('autosave-kept', 'Live');

		$response = $this->autosave('autosave-kept', 'Edited');

		$this->assertResponseOk($response);
		$this->assertSame('Edited', $this->autosavedTitle('autosave-kept'));
		$this->assertSame('Live', $this->liveTitle('autosave-kept'));
		$this->assertSame(0, $this->rows('drafts', 'autosave-kept'));
		$this->assertSame(0, $this->rows('nodes_history', 'autosave-kept'));
	}

	public function testEditsBackToTheStoredStateLeaveNoAutosave(): void
	{
		$this->createPage('autosave-undone', 'Live');
		$this->autosave('autosave-undone', 'Edited');

		$this->assertResponseOk($this->autosave('autosave-undone', 'Live'));

		$this->assertSame(0, $this->rows('autosaves', 'autosave-undone'));
	}

	public function testAnIncompleteFormIsNotKept(): void
	{
		$this->createPage('autosave-incomplete', 'Live');

		$response = $this->makeRequest('POST', '/panel/node/autosave-incomplete/autosave', [
			'headers' => ['HX-Request' => 'true'],
			'body' => ['content' => ['title' => ['value' => ['en' => 'Edited']]]],
		]);

		$this->assertSame(422, $response->getStatusCode());
		$this->assertSame(0, $this->rows('autosaves', 'autosave-incomplete'));
	}

	public function testTheEditorOpensWithTheAutosave(): void
	{
		$this->createPage('autosave-restored', 'Live');
		$this->autosave('autosave-restored', 'Edited');

		$editor = $this->editor('autosave-restored');

		$this->assertHtmlNodeExists('//input[@name="content[title][value][en]"][@value="Edited"]', $editor);
		$this->assertHtmlNodeExists('//*[@id="editor-autosave"][@data-restored]', $editor);
		$this->assertHtmlNodeExists(
			"//*[@id='editor-status-dots']/*[@data-stage='mine' and @data-state='present']",
			$editor,
		);
		$this->assertHtmlNodeExists('//*[@id="editor-conflict"][@hidden]', $editor);
		$this->assertSame('Live', $this->liveTitle('autosave-restored'));
	}

	public function testTheRestoredEditorMarksWhatTheAutosaveChanges(): void
	{
		$this->createPage('autosave-marked', 'Live');
		$this->autosave('autosave-marked', 'Edited');

		$restored = $this->editor('autosave-marked');

		$this->assertHtmlNodeExists(
			'//form[@id="editor-autosave-discard"][contains(@action, "/autosave/discard")]',
			$restored,
		);
		$this->assertHtmlNodeExists('//*[@data-field="title"]//*[@data-restored-mark]', $restored);
		$this->assertSame(1, substr_count($restored, 'data-restored-mark'));
	}

	public function testTheRestoredEditorMarksChangedSettingsAndTheirTab(): void
	{
		$this->createPage('autosave-settings', 'Live');
		$this->autosave('autosave-settings', 'Live', settings: ['handle' => 'renamed']);

		$restored = $this->editor('autosave-settings');

		$this->assertHtmlNodeExists('//label[@for="cms-node-handle"]/*[@data-restored-mark]', $restored);
		$this->assertHtmlNodeExists('//*[@id="cms-inspector-tab-advanced"]/*[@data-restored-mark]', $restored);
		$this->assertSame(2, substr_count($restored, 'data-restored-mark'));
	}

	public function testAnAutosaveFromBeforeTheLastSaveIsAConflict(): void
	{
		$this->createPage('autosave-stale', 'Live');
		$this->autosave('autosave-stale', 'Edited', base: '2000-01-01T00:00:00Z');

		$editor = $this->editor('autosave-stale');

		$this->assertHtmlNodeExists(
			"//*[@id='editor-status-dots']/*[@data-stage='mine' and @data-state='conflict']",
			$editor,
		);
		$this->assertHtmlNodeExists(
			'//*[@id="editor-conflict"][not(@hidden)]//button[@form="editor-autosave-discard"]',
			$editor,
		);
		$this->assertHtmlNodeExists('//*[@data-save-label][contains(., "Save anyway")]', $editor);
		$this->assertHtmlNodeExists('//input[@name="_base"][starts-with(@value, "2000-01-01")]', $editor);
	}

	public function testASaveOnAnOutdatedStateIsRefusedUnlessForced(): void
	{
		$this->createPage('autosave-locked', 'Live');
		$body = [
			'content' => ['title' => ['value' => ['en' => 'Mine']]],
			'_complete' => '1',
			'_base' => '2000-01-01T00:00:00Z',
		];

		$refused = $this->makeRequest('POST', '/panel/node/autosave-locked', [
			'headers' => ['HX-Request' => 'true'],
			'body' => $body,
		]);
		$published = $this->makeRequest('POST', '/panel/node/autosave-locked', [
			'headers' => ['HX-Request' => 'true'],
			'body' => [...$body, 'force' => '1', 'publish' => '1'],
		]);

		$this->assertHtmlNodeExists('//*[@data-toast="error"]', $this->getHtmlResponse($refused));
		$this->assertHtmlNodeExists(
			'//*[@id="editor-conflict" and @hx-swap-oob="true" and not(@hidden)]',
			$this->getHtmlResponse($refused),
		);
		$this->assertSame('Mine', $this->autosavedTitle('autosave-locked'));
		$this->assertHtmlNodeExists('//*[@data-toast="error"]', $this->getHtmlResponse($published));
		$this->assertSame('Live', $this->liveTitle('autosave-locked'));

		$forced = $this->makeRequest('POST', '/panel/node/autosave-locked', [
			'headers' => ['HX-Request' => 'true'],
			'body' => [...$body, 'force' => '1'],
		]);

		$this->assertHtmlNodeExists('//*[@data-toast="success"]', $this->getHtmlResponse($forced));
		$this->assertSame(0, $this->rows('autosaves', 'autosave-locked'));
	}

	public function testSavingDropsTheAutosaveAndRenewsTheBase(): void
	{
		$this->createPage('autosave-saved', 'Live');
		$this->autosave('autosave-saved', 'Edited');

		$response = $this->makeRequest('POST', '/panel/node/autosave-saved', [
			'headers' => ['HX-Request' => 'true'],
			'body' => ['content' => ['title' => ['value' => ['en' => 'Edited']]], '_complete' => '1'],
		]);

		$this->assertResponseOk($response);
		$this->assertSame(0, $this->rows('autosaves', 'autosave-saved'));
		$this->assertHtmlNodeExists(
			'//*[@id="editor-autosave"][@hx-swap-oob="true"][@data-saved][@data-base!=""]',
			$this->getHtmlResponse($response),
		);
	}

	public function testDiscardingDropsTheAutosave(): void
	{
		$this->createPage('autosave-discarded', 'Live');
		$this->autosave('autosave-discarded', 'Edited');

		$this->makeRequest('POST', '/panel/node/autosave-discarded/discard', ['headers' => ['HX-Request' => 'true']]);

		$this->assertSame(0, $this->rows('autosaves', 'autosave-discarded'));
	}

	public function testDiscardingTheAutosaveKeepsTheNode(): void
	{
		$this->createPage('autosave-dropped', 'Live');
		$this->autosave('autosave-dropped', 'Edited');

		$response = $this->makeRequest('POST', '/panel/node/autosave-dropped/autosave/discard', [
			'headers' => ['Accept' => 'application/json', 'X-Requested-With' => 'xmlhttprequest'],
		]);

		$this->assertResponseOk($response);
		$this->assertSame(0, $this->rows('autosaves', 'autosave-dropped'));
		$this->assertSame('Live', $this->liveTitle('autosave-dropped'));
	}

	public function testDiscardingTheRestoredEditsLoadsTheStoredState(): void
	{
		$this->createPage('autosave-reloaded', 'Live');
		$this->autosave('autosave-reloaded', 'Edited');

		$response = $this->makeRequest('POST', '/panel/node/autosave-reloaded/autosave/discard', [
			'headers' => ['HX-Request' => 'true'],
		]);

		$this->assertSame(0, $this->rows('autosaves', 'autosave-reloaded'));
		$location = json_decode($response->getHeaderLine('HX-Location'), true);
		$this->assertSame('/panel/node/autosave-reloaded', $location['path']);
		$this->assertSame('#editor-autosave-discard', $location['source']);
	}

	/** @param array<string, mixed> $settings */
	private function autosave(string $uid, string $title, string $base = '', array $settings = []): object
	{
		return $this->makeRequest('POST', '/panel/node/' . $uid . '/autosave', [
			'headers' => ['HX-Request' => 'true'],
			'body' => [
				'content' => ['title' => ['value' => ['en' => $title]]],
				...$settings,
				'_complete' => '1',
				'_base' => $base,
			],
		]);
	}

	private function editor(string $uid, string $query = ''): string
	{
		return (string) $this->makeRequest('GET', '/panel/node/' . $uid . $query)->getBody();
	}

	private function createPage(string $uid, string $title): void
	{
		$this->createTestNode([
			'uid' => $uid,
			'type' => (int) $this->db()->execute(
				"SELECT type FROM cms.types WHERE handle = 'test-page'",
			)->one()['type'],
			'published' => true,
			'content' => ['title' => ['type' => Text::class, 'value' => ['en' => $title]]],
		]);
	}

	private function liveTitle(string $uid): string
	{
		$row = $this->db()->execute('SELECT content FROM cms.nodes WHERE uid = :uid', ['uid' => $uid])->one();

		return json_decode((string) $row['content'], true)['title']['value']['en'];
	}

	private function autosavedTitle(string $uid): string
	{
		$row = $this->db()->execute(
			'SELECT a.content FROM cms.autosaves a JOIN cms.nodes n ON n.node = a.node WHERE n.uid = :uid',
			['uid' => $uid],
		)->one();

		return json_decode((string) $row['content'], true)['title']['value']['en'];
	}

	private function rows(string $table, string $uid): int
	{
		return (int) $this->db()->execute(
			"SELECT count(*) AS n FROM cms.{$table} t JOIN cms.nodes n ON n.node = t.node WHERE n.uid = :uid",
			['uid' => $uid],
		)->one()['n'];
	}
}
