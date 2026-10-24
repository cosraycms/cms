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
final class ApiPreviewTest extends End2EndTestCase
{
	private const string FRONTEND = 'https://front.example/preview?uid={uid}&token={token}&locale={locale}';

	protected function setUp(): void
	{
		parent::setUp();

		$this->app = $this->createApp([
			'api.path' => '/api',
			'api.preview' => self::FRONTEND,
			'app.secret' => 'preview-secret',
		]);
		$this->loadFixtures('basic-types');
		$this->authenticateAs('editor');
	}

	public function testThePanelPreviewHandsTheFrontendATokenForTheWorkingCopy(): void
	{
		$this->page('previewed', 'Live', published: true);

		$src = $this->previewSource('previewed', 'Pending');
		$this->assertStringStartsWith('https://front.example/preview?uid=previewed&token=', $src);
		parse_str((string) parse_url($src, PHP_URL_QUERY), $query);

		$response = $this->makeRequest('GET', '/api/nodes/previewed', [
			'authToken' => '',
			'query' => ['preview' => $query['token']],
		]);

		$this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
		$this->assertSame('Pending', json_decode((string) $response->getBody(), true)['fields']['title']);
		$this->assertSame('private, no-store', $response->getHeaderLine('Cache-Control'));
		$this->assertSame(
			'Live',
			json_decode((string) $this->makeRequest('GET', '/api/nodes/previewed', [
				'authToken' => '',
			])->getBody(), true)['fields']['title'],
		);
	}

	public function testATokenOpensOnlyItsOwnNode(): void
	{
		$this->page('first', 'First', published: false);
		$this->page('second', 'Second', published: false);
		parse_str((string) parse_url($this->previewSource('first', 'First draft'), PHP_URL_QUERY), $query);

		$response = $this->makeRequest('GET', '/api/nodes/second', [
			'authToken' => '',
			'query' => ['preview' => $query['token']],
		]);
		$garbage = $this->makeRequest('GET', '/api/nodes/first', [
			'authToken' => '',
			'query' => ['preview' => 'garbage'],
		]);

		$this->assertSame(401, $response->getStatusCode());
		$this->assertSame(401, $garbage->getStatusCode());
	}

	public function testWithoutASecretThePanelKeepsItsOwnPreview(): void
	{
		$this->app = $this->createApp(['api.path' => '/api', 'api.preview' => self::FRONTEND, 'app.secret' => null]);
		$this->page('own-preview', 'Live', published: true);

		$this->assertSame('/preview/own-preview', $this->previewSource('own-preview', 'Pending'));
	}

	private function previewSource(string $uid, string $title): string
	{
		$response = $this->makeRequest('POST', '/panel/node/' . $uid, [
			'headers' => ['HX-Request' => 'true'],
			'body' => ['preview' => '1', 'content' => ['title' => ['value' => ['en' => $title]]], '_complete' => '1'],
		]);
		$html = $this->getHtmlResponse($response);
		$this->assertSame(1, preg_match('/<iframe src="([^"]+)"/', $html, $match), $html);

		return html_entity_decode($match[1]);
	}

	private function page(string $uid, string $title, bool $published): void
	{
		$type = (int) $this->db()->execute("SELECT type FROM cms.types WHERE handle = 'test-page'")->one()['type'];
		$nodeId = $this->createTestNode([
			'uid' => $uid,
			'type' => $type,
			'published' => $published,
			'content' => ['title' => ['type' => Text::class, 'value' => ['en' => $title]]],
		]);
		$this->createTestPath($nodeId, "/test/{$uid}");
	}
}
