<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Cosray\Tests\End2EndTestCase;
use Laminas\Diactoros\UploadedFile;
use Psr\Http\Message\UploadedFileInterface;

/**
 * @internal
 *
 * @covers \Cosray\Controller\Media::upload
 */
final class MediaUploadTest extends End2EndTestCase
{
	private const string PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

	private string $publicDir;

	protected function setUp(): void
	{
		parent::setUp();
		$this->publicDir = sys_get_temp_dir() . '/cosray-upload-' . bin2hex(random_bytes(4));
		mkdir("{$this->publicDir}/assets", 0o755, true);
		$this->app = $this->createApp(['path.public' => $this->publicDir]);
		$this->authenticateAs('editor');
	}

	protected function tearDown(): void
	{
		$this->db()->execute(
			"DELETE FROM cms.assets WHERE filename LIKE 'e2e-upload-%'",
		)->run();
		$this->removeDir($this->publicDir);
		parent::tearDown();
	}

	public function testUploadStoresFileAndCatalogRow(): void
	{
		$png = base64_decode(self::PNG_BASE64, true);
		$response = $this->makeRequest('POST', '/media/image', [
			'files' => ['file' => $this->uploadedFile($png, 'e2e-upload-pic.png', 'image/png')],
		]);

		$this->assertResponseOk($response);
		$json = $this->getJsonResponse($response);
		$this->assertTrue($json['ok']);
		$uid = (string) $json['uid'];
		$this->assertSame(13, strlen($uid));
		$this->assertSame('e2e-upload-pic.png', $json['filename']);
		$this->assertSame('image/png', $json['mime']);
		$this->assertSame(1, $json['width']);
		$this->assertSame(1, $json['height']);

		$shard = substr($uid, 0, 2);
		$this->assertSame("/assets/{$shard}/{$uid}/e2e-upload-pic.png", $json['url']);
		$this->assertSame("/cache/{$shard}/{$uid}/e2e-upload-pic-thumb.png", $json['thumbUrl']);
		$this->assertSame("/cache/{$shard}/{$uid}/e2e-upload-pic-preview.png", $json['previewUrl']);

		// The URL is the file's path below public/ — the native serving contract.
		$stored = $this->publicDir . $json['url'];
		$this->assertFileExists($stored);
		$this->assertSame($png, file_get_contents($stored));

		$row = $this->db()->execute(
			'SELECT * FROM cms.assets WHERE uid = :uid',
			['uid' => $uid],
		)->one();
		$this->assertNotEmpty($row);
		$this->assertSame("{$shard}/{$uid}/e2e-upload-pic.png", $row['key']);
		$this->assertSame('local', $row['disk']);
		$this->assertSame('image', $row['kind']);
		$this->assertSame('image/png', $row['mime']);
		$this->assertSame(strlen($png), (int) $row['bytes']);
		$this->assertSame(hash('sha256', $png), $row['hash']);
	}

	public function testDuplicateUploadReturnsExistingAsset(): void
	{
		$png = base64_decode(self::PNG_BASE64, true);
		$first = $this->getJsonResponse($this->makeRequest('POST', '/media/image', [
			'files' => ['file' => $this->uploadedFile($png, 'e2e-upload-one.png', 'image/png')],
		]));
		$second = $this->getJsonResponse($this->makeRequest('POST', '/media/image', [
			'files' => ['file' => $this->uploadedFile($png, 'e2e-upload-two.png', 'image/png')],
		]));

		$this->assertTrue($first['ok']);
		$this->assertTrue($second['ok']);
		$this->assertSame($first['uid'], $second['uid']);
		// The duplicate keeps the catalog entry of the first upload.
		$this->assertSame('e2e-upload-one.png', $second['filename']);

		$count = $this->db()->execute(
			'SELECT count(*) AS count FROM cms.assets WHERE hash = :hash',
			['hash' => hash('sha256', $png)],
		)->one();
		$this->assertSame(1, (int) $count['count']);
	}

	public function testSvgIsSanitizedBeforeHashingAndStorage(): void
	{
		$svg =
			'<?xml version="1.0" encoding="UTF-8"?>'
			. '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10">'
			. '<script>alert(1)</script><rect width="10" height="10"/></svg>';
		$response = $this->makeRequest('POST', '/media/image', [
			'files' => ['file' => $this->uploadedFile($svg, 'e2e-upload-vec.svg', 'image/svg+xml')],
		]);

		$this->assertResponseOk($response);
		$json = $this->getJsonResponse($response);
		$this->assertTrue($json['ok']);

		$uid = (string) $json['uid'];
		// Non-resizable assets keep their original URL as thumb.
		$this->assertSame($json['url'], $json['thumbUrl']);
		$stored = (string) file_get_contents(
			"{$this->publicDir}/assets/" . substr($uid, 0, 2) . "/{$uid}/e2e-upload-vec.svg",
		);
		$this->assertStringNotContainsString('<script', $stored);
		$this->assertStringContainsString('<rect', $stored);

		$row = $this->db()->execute(
			'SELECT hash, bytes FROM cms.assets WHERE uid = :uid',
			['uid' => $uid],
		)->one();
		$this->assertSame(hash('sha256', $stored), $row['hash']);
		$this->assertSame(strlen($stored), (int) $row['bytes']);
	}

	public function testUploadRejectsDisallowedMime(): void
	{
		$response = $this->makeRequest('POST', '/media/image', [
			'files' => ['file' => $this->uploadedFile('plain text', 'e2e-upload-not.png', 'text/plain')],
		]);

		$this->assertResponseStatus(400, $response);
		$json = $this->getJsonResponse($response);
		$this->assertFalse($json['ok']);
		$this->assertStringContainsString('not allowed', (string) $json['error']);

		$this->assertEmpty(glob("{$this->publicDir}/assets/*/*"));
	}

	public function testUploadWithoutFileFails(): void
	{
		$response = $this->makeRequest('POST', '/media/image');

		$this->assertResponseStatus(400, $response);
		$json = $this->getJsonResponse($response);
		$this->assertFalse($json['ok']);
		$this->assertStringContainsString('Upload failed', (string) $json['error']);
	}

	public function testUploadRequiresAuthentication(): void
	{
		$png = base64_decode(self::PNG_BASE64, true);
		$response = $this->makeRequest('POST', '/media/image', [
			'authToken' => '',
			'files' => ['file' => $this->uploadedFile($png, 'e2e-upload-anon.png', 'image/png')],
		]);

		$this->assertResponseStatus(401, $response);
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
