<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Cosray\Tests\End2EndTestCase;
use Laminas\Diactoros\UploadedFile;
use Psr\Http\Message\UploadedFileInterface;

/**
 * @internal
 *
 * @covers \Cosray\Controller\Media::delete
 */
final class MediaDeleteTest extends End2EndTestCase
{
	private const string PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

	private string $publicDir;

	protected function setUp(): void
	{
		parent::setUp();
		$this->publicDir = sys_get_temp_dir() . '/cosray-delete-' . bin2hex(random_bytes(4));
		mkdir("{$this->publicDir}/assets", 0o755, true);
		$this->app = $this->createApp(['path.public' => $this->publicDir]);
		$this->authenticateAs('editor');
	}

	protected function tearDown(): void
	{
		$this->db()->execute(
			"DELETE FROM cms.asset_references WHERE owner_uid LIKE 'e2e-delete-%'",
		)->run();
		$this->db()->execute(
			"DELETE FROM cms.assets WHERE filename LIKE 'e2e-delete-%'",
		)->run();
		$this->removeDir($this->publicDir);
		parent::tearDown();
	}

	public function testDeleteRemovesRowFilesAndRenditions(): void
	{
		$uid = $this->upload('e2e-delete-gone.png');
		$shard = substr($uid, 0, 2);
		$assetDir = "{$this->publicDir}/assets/{$shard}/{$uid}";
		$cacheDir = "{$this->publicDir}/cache/{$shard}/{$uid}";
		mkdir($cacheDir, 0o755, true);
		file_put_contents("{$cacheDir}/e2e-delete-gone-thumb.png", 'rendition');

		$this->assertDirectoryExists($assetDir);

		$response = $this->makeRequest('DELETE', "/media/{$uid}");

		$this->assertResponseOk($response);
		$this->assertTrue($this->getJsonResponse($response)['ok']);
		$this->assertSame(
			[],
			$this->db()->execute('SELECT 1 FROM cms.assets WHERE uid = :uid', ['uid' => $uid])->all(),
		);
		$this->assertDirectoryDoesNotExist($assetDir);
		$this->assertDirectoryDoesNotExist($cacheDir);
	}

	public function testDeleteAnswersUsageWhenReferenced(): void
	{
		$uid = $this->upload('e2e-delete-used.png');
		$this->db()->execute(
			"INSERT INTO cms.asset_references (owner_type, owner_uid, asset_uid)
			VALUES ('node', 'e2e-delete-owner', :uid)",
			['uid' => $uid],
		)->run();

		$response = $this->makeRequest('DELETE', "/media/{$uid}");

		$this->assertSame(409, $response->getStatusCode());
		$json = $this->getJsonResponse($response);
		$this->assertFalse($json['ok']);
		$this->assertCount(1, $json['usage']);
		$this->assertSame('node', $json['usage'][0]['ownerType']);
		$this->assertSame('e2e-delete-owner', $json['usage'][0]['ownerUid']);

		// Row and file survive a blocked delete.
		$this->assertNotEmpty(
			$this->db()->execute('SELECT 1 FROM cms.assets WHERE uid = :uid', ['uid' => $uid])->all(),
		);
	}

	public function testDeleteUnknownUidAnswers404(): void
	{
		$response = $this->makeRequest('DELETE', '/media/nope-no-such-uid');

		$this->assertSame(404, $response->getStatusCode());
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

		return new UploadedFile($stream, strlen($contents), UPLOAD_ERR_OK, $filename, $mediaType);
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
