<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Cosray\Tests\End2EndTestCase;
use Laminas\Diactoros\UploadedFile;
use Psr\Http\Message\UploadedFileInterface;

/**
 * @internal
 *
 * @covers \Cosray\Controller\Media::library
 */
final class MediaLibraryTest extends End2EndTestCase
{
	private const string PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

	private string $publicDir;

	protected function setUp(): void
	{
		parent::setUp();
		$this->publicDir = sys_get_temp_dir() . '/cosray-library-' . bin2hex(random_bytes(4));
		mkdir("{$this->publicDir}/assets", 0o755, true);
		$this->app = $this->createApp(['path.public' => $this->publicDir]);
		$this->authenticateAs('editor');
	}

	protected function tearDown(): void
	{
		$this->db()->execute(
			"DELETE FROM cms.assets WHERE filename LIKE 'e2e-library-%'",
		)->run();
		$this->removeDir($this->publicDir);
		parent::tearDown();
	}

	public function testListsUploadedAssetsWithKindFilter(): void
	{
		$png = base64_decode(self::PNG_BASE64, true);
		$pdf = "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n";
		$image = $this->upload('image', $png, 'e2e-library-pic.png', 'image/png');
		$file = $this->upload('file', $pdf, 'e2e-library-doc.pdf', 'application/pdf');

		$all = $this->getJsonResponse($this->makeRequest('GET', '/media/library'));
		$uids = array_column($all['assets'], 'uid');
		$this->assertContains($image['uid'], $uids);
		$this->assertContains($file['uid'], $uids);

		$images = $this->getJsonResponse($this->makeRequest('GET', '/media/library', [
			'query' => ['kind' => 'image'],
		]));
		$imageUids = array_column($images['assets'], 'uid');
		$this->assertContains($image['uid'], $imageUids);
		$this->assertNotContains($file['uid'], $imageUids);

		// A File field accepts every kind, so `file` must not filter.
		$files = $this->getJsonResponse($this->makeRequest('GET', '/media/library', [
			'query' => ['kind' => 'file'],
		]));
		$fileUids = array_column($files['assets'], 'uid');
		$this->assertContains($image['uid'], $fileUids);
		$this->assertContains($file['uid'], $fileUids);
	}

	public function testListItemsCarryUrlsAndThumbs(): void
	{
		$png = base64_decode(self::PNG_BASE64, true);
		$image = $this->upload('image', $png, 'e2e-library-pic.png', 'image/png');

		$result = $this->getJsonResponse($this->makeRequest('GET', '/media/library', [
			'query' => ['q' => 'e2e-library-pic'],
		]));

		$this->assertCount(1, $result['assets']);
		$item = $result['assets'][0];
		$this->assertSame($image['uid'], $item['uid']);
		$this->assertSame('e2e-library-pic.png', $item['filename']);
		$this->assertSame("/media/image/{$image['uid']}/e2e-library-pic.png", $item['url']);
		$this->assertSame($item['url'] . '?resize=width&w=400', $item['thumbUrl']);
		$this->assertSame('image', $item['kind']);
	}

	public function testSearchMatchesFilename(): void
	{
		$png = base64_decode(self::PNG_BASE64, true);
		$this->upload('image', $png, 'e2e-library-pic.png', 'image/png');

		$missed = $this->getJsonResponse($this->makeRequest('GET', '/media/library', [
			'query' => ['q' => 'e2e-library-nomatch'],
		]));

		$this->assertSame([], $missed['assets']);
	}

	public function testLibraryRequiresAuthentication(): void
	{
		$response = $this->makeRequest('GET', '/media/library', ['authToken' => '']);

		$this->assertResponseStatus(401, $response);
	}

	private function upload(string $type, string $contents, string $filename, string $mime): array
	{
		$response = $this->makeRequest('POST', "/media/{$type}", [
			'files' => ['file' => $this->uploadedFile($contents, $filename, $mime)],
		]);
		$json = $this->getJsonResponse($response);
		$this->assertTrue($json['ok'], (string) ($json['error'] ?? ''));

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
