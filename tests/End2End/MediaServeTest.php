<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Cosray\Tests\End2EndTestCase;
use Laminas\Diactoros\UploadedFile;
use Psr\Http\Message\UploadedFileInterface;

/**
 * @internal
 *
 * @covers \Cosray\Controller\Media::file
 * @covers \Cosray\Controller\Media::image
 */
final class MediaServeTest extends End2EndTestCase
{
	private const string PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

	private string $publicDir;

	protected function setUp(): void
	{
		parent::setUp();
		$this->publicDir = sys_get_temp_dir() . '/cosray-serve-' . bin2hex(random_bytes(4));
		mkdir("{$this->publicDir}/assets", 0o755, true);
		mkdir("{$this->publicDir}/cache", 0o755, true);
		$this->app = $this->createApp(['path.public' => $this->publicDir]);
		$this->authenticateAs('editor');
	}

	protected function tearDown(): void
	{
		$this->db()->execute(
			"DELETE FROM cms.assets WHERE filename LIKE 'e2e-serve-%'",
		)->run();
		$this->removeDir($this->publicDir);
		parent::tearDown();
	}

	public function testServesUploadedImageByUid(): void
	{
		$png = base64_decode(self::PNG_BASE64, true);
		$upload = $this->upload($png, 'e2e-serve-pic.png', 'image/png');

		$response = $this->makeRequest('GET', $upload['url']);

		$this->assertResponseOk($response);
		$this->assertSame($png, (string) $response->getBody());
	}

	public function testNameSegmentIsCosmetic(): void
	{
		$png = base64_decode(self::PNG_BASE64, true);
		$upload = $this->upload($png, 'e2e-serve-pic.png', 'image/png');

		$response = $this->makeRequest('GET', "/media/image/{$upload['uid']}/anything.png");

		$this->assertResponseOk($response);
		$this->assertSame($png, (string) $response->getBody());
	}

	public function testResizeParametersStillApply(): void
	{
		$png = base64_decode(self::PNG_BASE64, true);
		$upload = $this->upload($png, 'e2e-serve-pic.png', 'image/png');

		$response = $this->makeRequest('GET', $upload['url'], [
			'query' => ['resize' => 'width', 'w' => '1'],
		]);

		$this->assertResponseOk($response);
		$info = getimagesizefromstring((string) $response->getBody());
		$this->assertSame(1, $info[0]);
	}

	public function testUnknownUidIs404(): void
	{
		$this->assertResponseStatus(
			404,
			$this->makeRequest('GET', '/media/image/nosuchuid1234/pic.png'),
		);
		$this->assertResponseStatus(
			404,
			$this->makeRequest('GET', '/media/file/nosuchuid1234/doc.pdf'),
		);
	}

	public function testLegacyOwnerScopedUrlIs404(): void
	{
		// Pre-1a URLs had the form /media/{type}/node/{ownerUid}/{filename};
		// the "node" segment now parses as an unknown asset uid.
		$this->assertResponseStatus(
			404,
			$this->makeRequest('GET', '/media/image/node/some-node/pic.jpg'),
		);
	}

	public function testFileWithMissingPoolEntryIs404(): void
	{
		$png = base64_decode(self::PNG_BASE64, true);
		// Files are also served through the file endpoint (e.g. downloads).
		$upload = $this->upload($png, 'e2e-serve-gone.png', 'image/png');
		$row = $this->db()->execute(
			'SELECT key FROM cms.assets WHERE uid = :uid',
			['uid' => $upload['uid']],
		)->one();
		unlink("{$this->publicDir}/assets/{$row['key']}");

		$response = $this->makeRequest('GET', "/media/file/{$upload['uid']}/gone.png");

		$this->assertResponseStatus(404, $response);
	}

	private function upload(string $contents, string $filename, string $mediaType): array
	{
		$response = $this->makeRequest('POST', '/media/image', [
			'files' => ['file' => $this->uploadedFile($contents, $filename, $mediaType)],
		]);
		$json = $this->getJsonResponse($response);
		$this->assertTrue($json['ok']);

		return $json;
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
