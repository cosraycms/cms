<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Cosray\Tests\End2EndTestCase;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Originals are served natively by the web server (URL == path below
 * public/), so only the rendition fallback route is PHP territory.
 *
 * @internal
 *
 * @covers \Cosray\Controller\Media::cache
 */
final class MediaServeTest extends End2EndTestCase
{
	private string $publicDir;

	protected function setUp(): void
	{
		parent::setUp();
		$this->publicDir = sys_get_temp_dir() . '/cosray-serve-' . bin2hex(random_bytes(4));
		mkdir("{$this->publicDir}/assets", 0o755, true);
		mkdir("{$this->publicDir}/cache", 0o755, true);
		$this->app = $this->createApp([
			'path.public' => $this->publicDir,
			'media.sizes' => ['square' => ['crop' => [2, 2]]],
		]);
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

	public function testUploadedOriginalIsAWebServerFile(): void
	{
		$png = $this->png(4, 4);
		$upload = $this->upload($png, 'e2e-serve-pic.png');

		// No dedicated route serves originals: the URL is the file's path
		// below public/, so the web server picks it up before PHP. In the
		// test app the page catchall's static-file fallback stands in.
		$this->assertFileExists($this->publicDir . $upload['url']);
		$this->assertSame($png, file_get_contents($this->publicDir . $upload['url']));

		$response = $this->makeRequest('GET', $upload['url']);
		$this->assertResponseOk($response);
		$this->assertSame($png, (string) $response->getBody());
	}

	public function testCacheMissGeneratesConfiguredRendition(): void
	{
		$upload = $this->upload($this->png(4, 4), 'e2e-serve-pic.png');
		$shard = substr((string) $upload['uid'], 0, 2);
		$url = "/cache/{$shard}/{$upload['uid']}/e2e-serve-pic-square.png";

		$response = $this->makeRequest('GET', $url);

		$this->assertResponseOk($response);
		$info = getimagesizefromstring((string) $response->getBody());
		$this->assertSame([2, 2], [$info[0], $info[1]]);

		// The rendition landed on its native URL for later requests.
		$this->assertFileExists($this->publicDir . $url);

		$again = $this->makeRequest('GET', $url);
		$this->assertResponseOk($again);
		$this->assertSame((string) $response->getBody(), (string) $again->getBody());
	}

	public function testBuiltinSizesAreAlwaysAvailable(): void
	{
		$upload = $this->upload($this->png(4, 4), 'e2e-serve-pic.png');

		$this->assertResponseOk($this->makeRequest('GET', $upload['thumbUrl']));
	}

	public function testUnknownSizeIs404(): void
	{
		$upload = $this->upload($this->png(4, 4), 'e2e-serve-pic.png');
		$shard = substr((string) $upload['uid'], 0, 2);

		$this->assertResponseStatus(404, $this->makeRequest(
			'GET',
			"/cache/{$shard}/{$upload['uid']}/e2e-serve-pic-w9999.png",
		));
	}

	public function testStaleFilenameIs404(): void
	{
		$upload = $this->upload($this->png(4, 4), 'e2e-serve-pic.png');
		$shard = substr((string) $upload['uid'], 0, 2);

		$this->assertResponseStatus(404, $this->makeRequest(
			'GET',
			"/cache/{$shard}/{$upload['uid']}/renamed-square.png",
		));
	}

	public function testUnknownUidIs404(): void
	{
		$this->assertResponseStatus(404, $this->makeRequest(
			'GET',
			'/cache/no/nosuchuid1234/pic-square.png',
		));
	}

	public function testLegacyMediaUrlIs404(): void
	{
		$upload = $this->upload($this->png(4, 4), 'e2e-serve-pic.png');

		$this->assertResponseStatus(404, $this->makeRequest(
			'GET',
			"/media/image/{$upload['uid']}/e2e-serve-pic.png",
		));
	}

	public function testMissingPoolFileIs404(): void
	{
		$upload = $this->upload($this->png(4, 4), 'e2e-serve-gone.png');
		$shard = substr((string) $upload['uid'], 0, 2);
		unlink($this->publicDir . $upload['url']);

		$this->assertResponseStatus(404, $this->makeRequest(
			'GET',
			"/cache/{$shard}/{$upload['uid']}/e2e-serve-gone-square.png",
		));
	}

	private function png(int $width, int $height): string
	{
		$image = imagecreatetruecolor($width, $height);
		ob_start();
		imagepng($image);

		return (string) ob_get_clean();
	}

	private function upload(string $contents, string $filename): array
	{
		$response = $this->makeRequest('POST', '/media/image', [
			'files' => ['file' => $this->uploadedFile($contents, $filename, 'image/png')],
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
