<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Cosray\Tests\End2EndTestCase;
use Psr\Http\Message\UploadedFileInterface;

/**
 * @internal
 *
 * @covers \Cosray\Controller\Media::detail
 * @covers \Cosray\Controller\Media::updateMeta
 */
final class MediaMetaTest extends End2EndTestCase
{
	private const string PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

	private string $publicDir;

	protected function setUp(): void
	{
		parent::setUp();
		$this->publicDir = sys_get_temp_dir() . '/cosray-meta-' . bin2hex(random_bytes(4));
		mkdir("{$this->publicDir}/assets", 0o755, true);
		$this->app = $this->createApp(['path.public' => $this->publicDir]);
		$this->authenticateAs('editor');
	}

	protected function tearDown(): void
	{
		$this->db()->execute(
			"DELETE FROM cms.asset_references WHERE owner_uid LIKE 'e2e-meta-%'",
		)->run();
		$this->db()->execute(
			"DELETE FROM cms.assets WHERE filename LIKE 'e2e-meta-%'",
		)->run();
		$this->removeDir($this->publicDir);
		parent::tearDown();
	}

	public function testDetailReturnsAssetMetaAndUsage(): void
	{
		$uid = $this->upload('e2e-meta-detail.png');

		$json = $this->getJsonResponse($this->makeRequest('GET', "/media/{$uid}"));

		$this->assertTrue($json['ok']);
		$this->assertSame($uid, $json['asset']['uid']);
		$this->assertSame('image', $json['asset']['kind']);
		$this->assertSame([], $json['asset']['meta']);
		$this->assertSame([], $json['usage']);

		$this->db()->execute(
			"INSERT INTO cms.asset_references (owner_type, owner_uid, asset_uid)
			VALUES ('node', 'e2e-meta-owner', :uid)",
			['uid' => $uid],
		)->run();

		$json = $this->getJsonResponse($this->makeRequest('GET', "/media/{$uid}"));
		$this->assertCount(1, $json['usage']);
		$this->assertSame('e2e-meta-owner', $json['usage'][0]['ownerUid']);
	}

	public function testUpdateMetaPersistsNormalizedValues(): void
	{
		$uid = $this->upload('e2e-meta-edit.png');

		$json = $this->getJsonResponse($this->makeRequest('PUT', "/media/{$uid}", [
			'body' => [
				'meta' => [
					'alt' => ['de' => ' Sudhaus ', 'en' => 'Brewhouse', 'xx' => 'dropped'],
					'title' => ['de' => ''],
					'credit' => 'Foto: M. Huber',
					'focal' => ['x' => 1.5, 'y' => 0.25],
				],
			],
		]));

		$this->assertTrue($json['ok']);
		$this->assertSame(['de' => 'Sudhaus', 'en' => 'Brewhouse'], $json['meta']['alt']);
		$this->assertArrayNotHasKey('title', $json['meta']);
		$this->assertSame('Foto: M. Huber', $json['meta']['credit']);
		// Whole-number floats collapse to int through JSON; compare loosely.
		$this->assertEquals(['x' => 1.0, 'y' => 0.25], $json['meta']['focal']);

		// Reload confirms the write landed and unknown locales were rejected.
		$reload = $this->getJsonResponse($this->makeRequest('GET', "/media/{$uid}"));
		$this->assertSame(['de' => 'Sudhaus', 'en' => 'Brewhouse'], $reload['asset']['meta']['alt']);

		// A second patch clearing alt removes it while keeping credit.
		$cleared = $this->getJsonResponse($this->makeRequest('PUT', "/media/{$uid}", [
			'body' => ['meta' => ['alt' => ['de' => '', 'en' => ''], 'credit' => 'Foto: M. Huber']],
		]));
		$this->assertArrayNotHasKey('alt', $cleared['meta']);
		$this->assertSame('Foto: M. Huber', $cleared['meta']['credit']);
	}

	public function testUnknownUidAnswers404(): void
	{
		$this->assertSame(404, $this->makeRequest('GET', '/media/nope-no-such-uid')->getStatusCode());
		$this->assertSame(
			404,
			$this->makeRequest('PUT', '/media/nope-no-such-uid', [
				'body' => ['meta' => []],
			])->getStatusCode(),
		);
	}

	private function upload(string $filename): string
	{
		$png = base64_decode(self::PNG_BASE64, true);
		$json = $this->getJsonResponse($this->makeRequest('POST', '/media/image', [
			'files' => ['file' => $this->uploadedFile($png, $filename, 'image/png')],
		]));
		$this->assertTrue($json['ok']);

		return (string) $json['uid'];
	}

	private function uploadedFile(
		string $contents,
		string $filename,
		string $mediaType,
	): UploadedFileInterface {
		$stream = $this->factory()->streamFactory()->createStream($contents);

		return $this->factory()->uploadedFile(
			$stream,
			strlen($contents),
			UPLOAD_ERR_OK,
			$filename,
			$mediaType,
		);
	}

	private function removeDir(string $dir): void
	{
		if (!is_dir($dir)) {
			return;
		}

		foreach (scandir($dir) ?: [] as $item) {
			if ($item === '.' || $item === '..') {
				continue;
			}

			$path = "{$dir}/{$item}";
			is_dir($path) && !is_link($path) ? $this->removeDir($path) : unlink($path);
		}

		rmdir($dir);
	}
}
