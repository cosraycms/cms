<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Cosray\Block\Types;
use Cosray\Bootstrap;
use Cosray\Config;
use Cosray\Field\Blocks;
use Cosray\Field\Text;
use Cosray\Field\Textarea;
use Cosray\Tests\End2EndTestCase;
use Cosray\Tests\Fixtures\Block\QuoteBlock;
use Cosray\Tests\Fixtures\Collection\TestArticlesCollection;
use Cosray\Tests\Fixtures\Node\TestAlternateEntry;
use Cosray\Tests\Fixtures\Node\TestConditionalDocument;
use Cosray\Tests\Fixtures\Node\TestEntry;
use Cosray\Tests\Fixtures\Node\TestNodeWithBlocks;
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
		$plugin->node(TestNodeWithBlocks::class);

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
										'type' => \Cosray\Field\Image::class,
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

	public function testAsymmetricBlocksPatchRowsByUidPerLocale(): void
	{
		$this->createBlocksNode('panel-save-blocks', 'test-media-document', [
			'contentBlocks' => [
				'type' => Blocks::class,
				'value' => [
					'en' => [$this->textBlock('block-a', 'Old EN', ['span' => 6, 'rows' => 1, 'indent' => 2])],
					'de' => [$this->textBlock('block-b', 'Alt DE', ['span' => 12, 'rows' => 1, 'indent' => 0])],
				],
			],
		]);

		$response = $this->makeRequest('POST', '/cp/collection/test-articles/panel-save-blocks', [
			'headers' => ['HX-Request' => 'true'],
			'body' => [
				'_complete' => '1',
				'content' => [
					'contentBlocks' => [
						'value' => [
							'en' => [
								[
									'uid' => '',
									'type' => Types\Heading::class,
									'layout' => ['span' => '12', 'rows' => '1', 'indent' => '0'],
									'fields' => [
										'text' => ['value' => ['zxx' => 'Fresh heading']],
										'level' => ['value' => ['zxx' => '3']],
									],
								],
								[
									'uid' => 'block-a',
									'type' => Types\Text::class,
									// Out of range: a twelve-column span with an indent left
									// over from a narrower one is clamped, not rejected.
									'layout' => ['span' => '14', 'rows' => '1', 'indent' => '2'],
									'fields' => ['text' => ['value' => ['zxx' => 'New EN']]],
									'meta' => ['class' => ['zxx' => 'hero'], 'id' => ['zxx' => 'intro']],
								],
							],
						],
					],
				],
			],
		]);

		$this->assertResponseOk($response);
		$value = $this->nodeContent('panel-save-blocks')['contentBlocks']['value'];

		$this->assertCount(2, $value['en']);
		$this->assertSame(Types\Heading::class, $value['en'][0]['type']);
		$this->assertMatchesRegularExpression('/^[123456789bcdfghklmnpqrstvwxyz]{13}$/', $value['en'][0]['uid']);
		$this->assertSame('3', $value['en'][0]['fields']['level']['value']['zxx']);
		$this->assertSame('block-a', $value['en'][1]['uid']);
		// jsonb orders keys; compare by content.
		$this->assertEquals(['span' => 12, 'rows' => 1, 'indent' => 0], $value['en'][1]['layout']);
		$this->assertSame('New EN', $value['en'][1]['fields']['text']['value']['zxx']);
		$this->assertSame('kept', $value['en'][1]['fields']['text']['stashed']);
		$this->assertEquals(['class' => ['zxx' => 'hero'], 'id' => ['zxx' => 'intro']], $value['en'][1]['meta']);
		// The German list was not part of the submission.
		$this->assertSame('Alt DE', $value['de'][0]['fields']['text']['value']['zxx']);
	}

	public function testSymmetricBlocksPatchTranslatedSubFieldsInsideTheSharedList(): void
	{
		$this->createBlocksNode('panel-save-blocks-symmetric', 'test-node-with-blocks', [
			'title' => ['type' => Text::class, 'value' => ['zxx' => 'With blocks']],
			'blocks' => [
				'type' => Blocks::class,
				'value' => ['zxx' => [$this->quoteBlock('quote-a', ['en' => 'Old EN', 'de' => 'Alt DE'])]],
			],
		]);

		$response = $this->makeRequest('POST', '/cp/collection/test-articles/panel-save-blocks-symmetric', [
			'headers' => ['HX-Request' => 'true'],
			'body' => [
				'_complete' => '1',
				'content' => [
					'title' => ['value' => ['zxx' => 'With blocks']],
					'blocks' => [
						'value' => [
							'zxx' => [[
								'uid' => 'quote-a',
								'type' => QuoteBlock::class,
								'layout' => ['span' => '1', 'rows' => '1', 'indent' => '0'],
								'fields' => ['text' => ['value' => ['de' => 'Neu DE']]],
							]],
						],
					],
				],
			],
		]);

		$this->assertResponseOk($response);
		$row = $this->nodeContent('panel-save-blocks-symmetric')['blocks']['value']['zxx'][0];

		$this->assertEquals(['en' => 'Old EN', 'de' => 'Neu DE'], $row['fields']['text']['value']);
		$this->assertSame('Someone', $row['fields']['source']['value']['zxx']);
	}

	public function testValidationErrorsInsideBlocksCarryTheRowPath(): void
	{
		$this->createBlocksNode('panel-save-blocks-invalid', 'test-node-with-blocks', [
			'title' => ['type' => Text::class, 'value' => ['zxx' => 'With blocks']],
			'blocks' => [
				'type' => Blocks::class,
				'value' => ['zxx' => [$this->quoteBlock('quote-a', ['en' => 'Valid', 'de' => ''])]],
			],
		]);

		// Everything else stays rule-clean on purpose: sire runs the row
		// review, which reports the sub-fields, only on rule-clean data.
		$response = $this->makeRequest('POST', '/cp/collection/test-articles/panel-save-blocks-invalid', [
			'headers' => ['HX-Request' => 'true'],
			'body' => [
				'_complete' => '1',
				'content' => [
					'title' => ['value' => ['zxx' => 'With blocks']],
					'blocks' => [
						'value' => [
							'zxx' => [[
								'uid' => 'quote-a',
								'type' => QuoteBlock::class,
								'layout' => ['span' => '1', 'rows' => '1', 'indent' => '0'],
								// Empties the required quote in the default locale.
								'fields' => ['text' => ['value' => ['en' => '']]],
							]],
						],
					],
				],
			],
		]);

		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);
		$this->assertHtmlNodeExists(
			'//*[@id="editor-status" and contains(concat(" ", normalize-space(@class), " "), " is-error ")]',
			$html,
		);
		$this->assertStringContainsString(
			'data-error-path=\'["content","blocks","value","zxx",0,"fields","text","value","en"]\'',
			$html,
		);
		$this->assertSame(
			'Valid',
			$this->nodeContent(
				'panel-save-blocks-invalid',
			)['blocks']['value']['zxx'][0]['fields']['text']['value']['en'],
		);
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
		// The out-of-band response replaces these by id, so its classes have to
		// stay the ones the editor styles — otherwise a save silently strips the
		// styling off the element it swaps.
		$this->assertHtmlNodeExists(
			'//*[@id="editor-status" and @hx-swap-oob="true" and contains(concat(" ", normalize-space(@class), " "), " status ") and contains(concat(" ", normalize-space(@class), " "), " is-success ")]',
			$html,
		);
		$this->assertHtmlNodeExists(
			'//*[@id="editor-errors" and contains(concat(" ", normalize-space(@class), " "), " errors ")]',
			$html,
		);

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
		$this->assertHtmlNodeExists(
			'//*[@id="editor-status" and contains(concat(" ", normalize-space(@class), " "), " status ") and contains(concat(" ", normalize-space(@class), " "), " is-error ")]',
			$html,
		);
		// The box is worth nothing if it arrives empty or hidden, which is
		// what asserting on its id alone let through.
		$this->assertHtmlNodeExists('//*[@id="editor-errors" and not(@hidden)]', $html);
		// Each issue is a jump target: the message plus the data path the
		// client resolves to the offending control.
		$this->assertStringContainsString(
			'data-error-path=\'["content","title","value","zxx"]\'',
			$html,
		);
		$this->assertStringContainsString('Document Title must be at least 3 characters', $html);
		$this->assertSame(
			'Valid Title',
			$this->nodeContent('panel-save-invalid')['title']['value']['zxx'],
		);
	}

	public function testValidationErrorsInsideEntriesCarryTheRowPath(): void
	{
		$entriesType = $this->db()->execute(
			"SELECT type FROM cms.types WHERE handle = 'test-node-with-entries'",
		)->first();
		$typeId = $entriesType
			? (int) $entriesType['type']
			: $this->createTestType('test-node-with-entries');
		$this->createTestNode([
			'uid' => 'panel-save-entries-invalid',
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
										'value' => ['en' => 'Valid'],
									],
								],
							],
						],
					],
				],
			],
		]);

		$response = $this->makeRequest('POST', '/cp/collection/test-articles/panel-save-entries-invalid', [
			'headers' => ['HX-Request' => 'true'],
			'body' => [
				'_complete' => '1',
				'content' => [
					'title' => ['value' => ['zxx' => 'With entries']],
					'entries' => [
						'value' => [
							'zxx' => [
								[
									'uid' => 'entry-a',
									'type' => TestEntry::class,
									// Empties the required title of the default locale.
									'fields' => ['title' => ['value' => ['en' => '']]],
								],
							],
						],
					],
				],
			],
		]);

		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);
		$this->assertHtmlNodeExists(
			'//*[@id="editor-status" and contains(concat(" ", normalize-space(@class), " "), " is-error ")]',
			$html,
		);
		// The row index rides in the path, so the client can point at the
		// control inside the right server-rendered entries row.
		$this->assertStringContainsString(
			'data-error-path=\'["content","entries","value","zxx",0,"fields","title","value","en"]\'',
			$html,
		);
		$this->assertSame(
			'Valid',
			$this->nodeContent(
				'panel-save-entries-invalid',
			)['entries']['value']['zxx'][0]['fields']['title']['value']['en'],
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
		$this->assertHtmlNodeExists(
			'//*[@id="editor-status" and contains(concat(" ", normalize-space(@class), " "), " is-error ")]',
			$html,
		);
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
		$this->assertHtmlNodeExists(
			'//*[@id="editor-status" and contains(concat(" ", normalize-space(@class), " "), " is-error ")]',
			$this->getHtmlResponse($response),
		);
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

	/**
	 * Typed block rows are stored pre-encoded: the helper's legacy content
	 * normalizer would rewrite them.
	 */
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
			'type' => Types\Text::class,
			'layout' => $layout,
			'fields' => ['text' => ['type' => Textarea::class, 'value' => ['zxx' => $text], 'stashed' => 'kept']],
		];
	}

	private function quoteBlock(string $uid, array $text): array
	{
		return [
			'uid' => $uid,
			'type' => QuoteBlock::class,
			'layout' => ['span' => 1, 'rows' => 1, 'indent' => 0],
			'fields' => [
				'text' => ['type' => Textarea::class, 'value' => $text],
				'source' => ['type' => Text::class, 'value' => ['zxx' => 'Someone']],
			],
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
