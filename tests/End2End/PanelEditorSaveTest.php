<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Cosray\Bootstrap;
use Cosray\Config;
use Cosray\Tests\End2EndTestCase;
use Cosray\Tests\Fixtures\Collection\TestArticlesCollection;
use Cosray\Tests\Fixtures\Node\TestAlternateEntry;
use Cosray\Tests\Fixtures\Node\TestConditionalDocument;
use Cosray\Tests\Fixtures\Node\TestEntry;
use Cosray\Tests\Fixtures\Node\TestNodeWithEntries;

final class PanelEditorSaveTest extends End2EndTestCase
{
	private ?int $articleTypeId = null;

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
		$plugin->node(TestConditionalDocument::class);
		$plugin->node(TestNodeWithEntries::class);

		return $plugin;
	}

	public function testEntriesSubmissionsPatchRowsByUid(): void
	{
		$entriesType = $this->db()->execute(
			"SELECT type FROM cms.types WHERE handle = 'test-node-with-entries'",
		)->first();
		$typeId = $entriesType
			? (int) $entriesType['type']
			: $this->createTestType('test-node-with-entries');
		$this->createTestNode([
			'uid' => 'panel-save-entries',
			'type' => $typeId,
			'published' => true,
			'content' => [
				'title' => ['type' => 'text', 'value' => ['zxx' => 'With entries']],
				'entries' => [
					'type' => \Cosray\Field\Entries::class,
					'value' => [
						'zxx' => [
							[
								'uid' => 'entry-a',
								'type' => TestEntry::class,
								'fields' => [
									'title' => [
										'type' => \Cosray\Field\Text::class,
										'value' => ['en' => 'Old title'],
									],
									'content' => [
										'type' => \Cosray\Field\Blocks::class,
										'value' => ['en' => []],
										'stashed' => 'kept',
									],
								],
							],
						],
					],
				],
			],
		]);

		$response = $this->makeRequest('POST', '/cp/collection/test-articles/panel-save-entries', [
			'headers' => ['HX-Request' => 'true'],
			'body' => [
				'_complete' => '1',
				'content' => [
					'title' => ['value' => ['zxx' => 'With entries']],
					'entries' => [
						'value' => [
							'zxx' => [
								[
									'uid' => '',
									'type' => TestAlternateEntry::class,
									'fields' => ['name' => ['value' => ['zxx' => 'Fresh']]],
								],
								[
									'uid' => 'entry-a',
									'type' => TestEntry::class,
									'fields' => ['title' => ['value' => ['en' => 'New title']]],
								],
							],
						],
					],
				],
			],
		]);

		$this->assertResponseOk($response);
		$rows = $this->nodeContent('panel-save-entries')['entries']['value']['zxx'];

		$this->assertCount(2, $rows);
		// The stamped row moved first and got a server-backfilled uid.
		$this->assertSame(TestAlternateEntry::class, $rows[0]['type']);
		$this->assertMatchesRegularExpression('/^[123456789bcdfghklmnpqrstvwxyz]{13}$/', $rows[0]['uid']);
		$this->assertSame('Fresh', $rows[0]['fields']['name']['value']['zxx']);
		// The stored row was patched by uid; unsubmitted sub-fields
		// survive. (Unknown keys inside a row's field entries do not —
		// storing validates rows via finalizeEntryValue, which keeps only
		// declared keys. Same behavior as the island path.)
		$this->assertSame('entry-a', $rows[1]['uid']);
		$this->assertSame('New title', $rows[1]['fields']['title']['value']['en']);
		$this->assertSame(['en' => []], $rows[1]['fields']['content']['value']);
	}

	public function testMetaSubmissionsPatchTheStoredMetaMap(): void
	{
		$conditionalType = $this->db()->execute(
			"SELECT type FROM cms.types WHERE handle = 'test-conditional-document'",
		)->first();
		$typeId = $conditionalType
			? (int) $conditionalType['type']
			: $this->createTestType('test-conditional-document');
		$this->createTestNode([
			'uid' => 'panel-save-meta',
			'type' => $typeId,
			'published' => true,
			'content' => [
				'styled' => [
					'type' => 'text',
					'value' => ['zxx' => 'Body'],
					'meta' => ['stashed' => ['zxx' => 'kept']],
				],
			],
		]);

		$response = $this->makeRequest('POST', '/cp/collection/test-articles/panel-save-meta', [
			'headers' => ['HX-Request' => 'true'],
			'body' => [
				'_complete' => '1',
				'content' => [
					'styled' => [
						'value' => ['zxx' => 'Body'],
						'meta' => ['cssClass' => ['zxx' => 'wide']],
					],
				],
			],
		]);

		$this->assertResponseOk($response);
		$content = $this->nodeContent('panel-save-meta');
		$this->assertSame('wide', $content['styled']['meta']['cssClass']['zxx']);
		$this->assertSame('kept', $content['styled']['meta']['stashed']['zxx']);
	}

	public function testHtmxSaveUpdatesSubmittedFieldsAndKeepsEverythingElse(): void
	{
		$this->createTestNode([
			'uid' => 'panel-save-a',
			'type' => $this->articleTypeId(),
			'published' => true,
			'content' => [
				'title' => [
					'type' => 'text',
					'value' => ['en' => 'Old Title', 'fr' => 'Ancien titre'],
					'meta' => ['stashed' => ['zxx' => 'kept']],
				],
			],
		]);

		$response = $this->makeRequest('POST', '/cp/collection/test-articles/panel-save-a', [
			'headers' => ['HX-Request' => 'true'],
			'body' => [
				'_complete' => '1',
				'content' => [
					'title' => ['value' => ['en' => 'New Title', 'de' => 'Neuer Titel']],
				],
			],
		]);

		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);
		$this->assertStringContainsString('id="editor-status"', $html);
		$this->assertStringContainsString('is-success', $html);
		$this->assertStringContainsString('hx-swap-oob="true"', $html);
		// The out-of-band response replaces these by id, so its classes have to
		// stay the ones the editor styles — otherwise a save silently strips the
		// styling off the element it swaps.
		$this->assertStringContainsString('class="status is-success"', $html);
		$this->assertStringContainsString('class="errors"', $html);

		$content = $this->nodeContent('panel-save-a');
		$this->assertSame('New Title', $content['title']['value']['en']);
		$this->assertSame('Neuer Titel', $content['title']['value']['de']);
		$this->assertSame('Ancien titre', $content['title']['value']['fr']);
		$this->assertSame(['zxx' => 'kept'], $content['title']['meta']['stashed']);
	}

	public function testPlainSaveRedirectsBackToTheEditor(): void
	{
		$this->createTestNode([
			'uid' => 'panel-save-plain',
			'type' => $this->articleTypeId(),
			'published' => true,
			'content' => [
				'title' => ['type' => 'text', 'value' => ['en' => 'Plain']],
			],
		]);

		$response = $this->makeRequest('POST', '/cp/collection/test-articles/panel-save-plain', [
			'body' => [
				'_complete' => '1',
				'content' => ['title' => ['value' => ['en' => 'Plain Updated']]],
			],
		]);

		$this->assertResponseStatus(303, $response);
		$this->assertSame(
			'/cp/collection/test-articles/panel-save-plain',
			$response->getHeaderLine('Location'),
		);
		$this->assertSame(
			'Plain Updated',
			$this->nodeContent('panel-save-plain')['title']['value']['en'],
		);
	}

	public function testHtmxSaveReportsValidationErrorsOutOfBand(): void
	{
		$documentType = $this->db()->execute(
			"SELECT type FROM cms.types WHERE handle = 'test-document'",
		)->one();
		$documentTypeId = $documentType
			? (int) $documentType['type']
			: $this->createTestType('test-document');
		$this->createTestNode([
			'uid' => 'panel-save-invalid',
			'type' => $documentTypeId,
			'published' => true,
			'content' => [
				'title' => ['type' => 'text', 'value' => ['zxx' => 'Valid Title']],
			],
		]);

		$response = $this->makeRequest('POST', '/cp/collection/test-articles/panel-save-invalid', [
			'headers' => ['HX-Request' => 'true'],
			'body' => [
				'_complete' => '1',
				// Violates the fixture's minLength:3 rule on the required title.
				'content' => ['title' => ['value' => ['zxx' => 'ab']]],
			],
		]);

		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);
		$this->assertStringContainsString('is-error', $html);
		$this->assertStringContainsString('id="editor-errors"', $html);
		$this->assertStringContainsString('class="status is-error"', $html);
		// The box is worth nothing if it arrives empty or hidden, which is
		// what asserting on its id alone let through.
		$this->assertDoesNotMatchRegularExpression('/id="editor-errors"[^>]*hidden/', $html);
		$this->assertStringContainsString(
			'<li>Document Title must be at least 3 characters</li>',
			$html,
		);
		$this->assertSame(
			'Valid Title',
			$this->nodeContent('panel-save-invalid')['title']['value']['zxx'],
		);
	}

	public function testSaveRejectsUnknownNode(): void
	{
		$response = $this->makeRequest('POST', '/cp/collection/test-articles/does-not-exist', [
			'headers' => ['HX-Request' => 'true'],
			'body' => ['_complete' => '1', 'content' => []],
		]);

		$this->assertResponseStatus(404, $response);
	}

	public function testUrlencodedFallbackSavesLikeTheJsonTransport(): void
	{
		$this->createTestNode([
			'uid' => 'panel-save-urlencoded',
			'type' => $this->articleTypeId(),
			'published' => true,
			'content' => [
				'title' => ['type' => 'text', 'value' => ['en' => 'Encoded']],
			],
		]);

		$response = $this->makeRequest('POST', '/cp/collection/test-articles/panel-save-urlencoded', [
			'headers' => [
				'HX-Request' => 'true',
				'Content-Type' => 'application/x-www-form-urlencoded',
			],
			'body' => http_build_query([
				'content' => ['title' => ['value' => ['en' => 'Encoded Updated']]],
				'_complete' => '1',
			]),
		]);

		$this->assertResponseOk($response);
		$this->assertSame(
			'Encoded Updated',
			$this->nodeContent('panel-save-urlencoded')['title']['value']['en'],
		);
	}

	public function testTruncatedSubmissionIsRefusedWithoutSaving(): void
	{
		$this->createTestNode([
			'uid' => 'panel-save-truncated',
			'type' => $this->articleTypeId(),
			'published' => true,
			'content' => [
				'title' => ['type' => 'text', 'value' => ['en' => 'Kept']],
			],
		]);

		// A POST truncated by max_input_vars loses its tail — including the
		// sentinel the editor form renders as its last control.
		$response = $this->makeRequest('POST', '/cp/collection/test-articles/panel-save-truncated', [
			'headers' => [
				'HX-Request' => 'true',
				'Content-Type' => 'application/x-www-form-urlencoded',
			],
			'body' => http_build_query([
				'content' => ['title' => ['value' => ['en' => 'Lost Update']]],
			]),
		]);

		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);
		$this->assertStringContainsString('class="status is-error"', $html);
		$this->assertStringContainsString('max_input_vars', $html);
		$this->assertSame(
			'Kept',
			$this->nodeContent('panel-save-truncated')['title']['value']['en'],
		);
	}

	public function testTruncatedNonHtmxSubmissionFailsHard(): void
	{
		$this->createTestNode([
			'uid' => 'panel-save-truncated-plain',
			'type' => $this->articleTypeId(),
			'published' => true,
			'content' => [
				'title' => ['type' => 'text', 'value' => ['en' => 'Kept']],
			],
		]);

		// Without htmx there is no error box to render into; a silent PRG
		// redirect would hide the data loss, so the save fails hard instead.
		$response = $this->makeRequest('POST', '/cp/collection/test-articles/panel-save-truncated-plain', [
			'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
			'body' => http_build_query([
				'content' => ['title' => ['value' => ['en' => 'Lost Update']]],
			]),
		]);

		$this->assertResponseStatus(400, $response);
		$this->assertSame(
			'Kept',
			$this->nodeContent('panel-save-truncated-plain')['title']['value']['en'],
		);
	}

	public function testJsonSubmissionWithoutSentinelIsRefused(): void
	{
		$this->createTestNode([
			'uid' => 'panel-save-nosentinel',
			'type' => $this->articleTypeId(),
			'published' => true,
			'content' => [
				'title' => ['type' => 'text', 'value' => ['en' => 'Kept']],
			],
		]);

		// The guard is transport-independent: a JSON body that lost the
		// sentinel (mangled or hand-built) is refused the same way.
		$response = $this->makeRequest('POST', '/cp/collection/test-articles/panel-save-nosentinel', [
			'headers' => ['HX-Request' => 'true'],
			'body' => [
				'content' => ['title' => ['value' => ['en' => 'Lost Update']]],
			],
		]);

		$this->assertResponseOk($response);
		$this->assertStringContainsString('class="status is-error"', $this->getHtmlResponse($response));
		$this->assertSame(
			'Kept',
			$this->nodeContent('panel-save-nosentinel')['title']['value']['en'],
		);
	}

	public function testPublishButtonAndSettingsFlagsAreApplied(): void
	{
		$this->createTestNode([
			'uid' => 'panel-save-publish',
			'type' => $this->articleTypeId(),
			'published' => false,
			'content' => [
				'title' => ['type' => 'text', 'value' => ['en' => 'Unpublished']],
			],
		]);

		$response = $this->makeRequest('POST', '/cp/collection/test-articles/panel-save-publish', [
			'headers' => ['HX-Request' => 'true'],
			'body' => [
				'_complete' => '1',
				'publish' => '1',
				'handle' => 'panel-save-handle',
				'content' => ['title' => ['value' => ['en' => 'Unpublished']]],
			],
		]);

		$this->assertResponseOk($response);
		$row = $this->db()->execute(
			'SELECT published FROM cms.nodes WHERE uid = :uid',
			['uid' => 'panel-save-publish'],
		)->one();
		$this->assertTrue((bool) $row['published']);

		$handle = $this->db()->execute(
			'SELECT h.handle FROM cms.node_handles h
				JOIN cms.nodes n ON n.node = h.node
				WHERE n.uid = :uid',
			['uid' => 'panel-save-publish'],
		)->one();
		$this->assertSame('panel-save-handle', $handle['handle'] ?? null);
	}

	public function testDeleteRedirectsToTheCollection(): void
	{
		$this->createTestNode([
			'uid' => 'panel-save-delete',
			'type' => $this->articleTypeId(),
			'published' => true,
			'content' => [
				'title' => ['type' => 'text', 'value' => ['en' => 'Doomed']],
			],
		]);

		$response = $this->makeRequest(
			'POST',
			'/cp/collection/test-articles/panel-save-delete/delete',
		);

		$this->assertResponseStatus(303, $response);
		$this->assertStringStartsWith(
			'/cp/collection/test-articles',
			$response->getHeaderLine('Location'),
		);

		$gone = $this->makeRequest('GET', '/cp/collection/test-articles/panel-save-delete');
		$this->assertResponseStatus(404, $gone);
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

	private function articleTypeId(): int
	{
		if ($this->articleTypeId !== null) {
			return $this->articleTypeId;
		}

		$type = $this->db()->execute(
			"SELECT type FROM cms.types WHERE handle = 'test-article'",
		)->one();
		$this->assertNotEmpty($type);
		$this->articleTypeId = (int) $type['type'];

		return $this->articleTypeId;
	}
}
