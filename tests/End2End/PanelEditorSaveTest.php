<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Cosray\Bootstrap;
use Cosray\Config;
use Cosray\Tests\End2EndTestCase;
use Cosray\Tests\Fixtures\Collection\TestArticlesCollection;

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

		return $plugin;
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
				// Violates the fixture's minLength:3 rule on the required title.
				'content' => ['title' => ['value' => ['zxx' => 'ab']]],
			],
		]);

		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);
		$this->assertStringContainsString('is-error', $html);
		$this->assertStringContainsString('id="editor-errors"', $html);
		$this->assertSame(
			'Valid Title',
			$this->nodeContent('panel-save-invalid')['title']['value']['zxx'],
		);
	}

	public function testSaveRejectsUnknownNode(): void
	{
		$response = $this->makeRequest('POST', '/cp/collection/test-articles/does-not-exist', [
			'headers' => ['HX-Request' => 'true'],
			'body' => ['content' => []],
		]);

		$this->assertResponseStatus(404, $response);
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

		$payload = json_decode(
			(string) $this->makeRequest('GET', '/panel/api/node/panel-save-publish')->getBody(),
			true,
		);
		$this->assertSame('panel-save-handle', $payload['handle']);
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
