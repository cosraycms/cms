<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Cosray\Tests\End2EndTestCase;
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
		try {
			$this->removeDir($this->publicDir);
		} finally {
			parent::tearDown();
		}
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
		$this->assertSame(2, $all['total']);

		$images = $this->getJsonResponse($this->makeRequest('GET', '/media/library', [
			'query' => ['kind' => 'image'],
		]));
		$imageUids = array_column($images['assets'], 'uid');
		$this->assertContains($image['uid'], $imageUids);
		$this->assertNotContains($file['uid'], $imageUids);
		$this->assertSame(1, $images['total']);

		// A File field accepts every kind, so `file` must not filter.
		$files = $this->getJsonResponse($this->makeRequest('GET', '/media/library', [
			'query' => ['kind' => 'file'],
		]));
		$fileUids = array_column($files['assets'], 'uid');
		$this->assertContains($image['uid'], $fileUids);
		$this->assertContains($file['uid'], $fileUids);
	}

	/**
	 * Projects routinely allow image mimes on the File field. Those uploads
	 * belong in the image filter: the kind follows the bytes, not the route
	 * that catalogued them.
	 */
	public function testAnImageUploadedThroughTheFileRouteIsStillAnImage(): void
	{
		$this->app = $this->createApp([
			'path.public' => $this->publicDir,
			'upload.mimetypes.file' => [
				'application/pdf' => ['pdf'],
				'image/png' => ['png'],
			],
		]);
		$this->authenticateAs('editor');

		$png = base64_decode(self::PNG_BASE64, true);
		$asset = $this->upload('file', $png, 'e2e-library-logo.png', 'image/png');

		$images = $this->getJsonResponse($this->makeRequest('GET', '/media/library', [
			'query' => ['kind' => 'image'],
		]));

		$this->assertContains($asset['uid'], array_column($images['assets'], 'uid'));
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
		$shard = substr((string) $image['uid'], 0, 2);
		$this->assertSame($image['uid'], $item['uid']);
		$this->assertSame('e2e-library-pic.png', $item['filename']);
		$this->assertSame("/assets/{$shard}/{$image['uid']}/e2e-library-pic.png", $item['url']);
		$this->assertSame(
			"/cache/{$shard}/{$image['uid']}/e2e-library-pic-thumb.png",
			$item['thumbUrl'],
		);
		$this->assertSame(
			"/cache/{$shard}/{$image['uid']}/e2e-library-pic-preview.png",
			$item['previewUrl'],
		);
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
		$this->assertSame(0, $missed['total']);
	}

	public function testKindFilterVocabularySplitsFileIntoAudioAndDocument(): void
	{
		$image = $this->insertAsset('vocab-pic.png', 'image/png');
		$video = $this->insertAsset('vocab-clip.mp4', 'video/mp4');
		$audio = $this->insertAsset('vocab-song.mp3', 'audio/mpeg');
		$document = $this->insertAsset('vocab-doc.pdf', 'application/pdf');
		$unknown = $this->insertAsset('vocab-blob.bin', null);

		$audios = $this->getJsonResponse($this->makeRequest('GET', '/media/library', [
			'query' => ['kind' => 'audio'],
		]));
		$this->assertSame([$audio], array_column($audios['assets'], 'uid'));

		// Unreadable mimes land on document, mirroring classify()'s fallback.
		$documents = $this->getJsonResponse($this->makeRequest('GET', '/media/library', [
			'query' => ['kind' => 'document'],
		]));
		$documentUids = array_column($documents['assets'], 'uid');
		$this->assertContains($document, $documentUids);
		$this->assertContains($unknown, $documentUids);
		$this->assertNotContains($audio, $documentUids);

		$mixed = $this->getJsonResponse($this->makeRequest('GET', '/media/library', [
			'query' => ['kind' => 'image,audio'],
		]));
		$mixedUids = array_column($mixed['assets'], 'uid');
		$this->assertContains($image, $mixedUids);
		$this->assertContains($audio, $mixedUids);
		$this->assertNotContains($video, $mixedUids);
		$this->assertSame(2, $mixed['total']);

		// Counts honor q/since but never the kind filter itself.
		$this->assertSame(
			['image' => 1, 'video' => 1, 'audio' => 1, 'document' => 2],
			$mixed['counts'],
		);
	}

	public function testSinceCutsOnTheCreatedTimestamp(): void
	{
		$old = $this->insertAsset('since-old.png', 'image/png', '2020-06-01T12:00:00+00:00');
		$fresh = $this->insertAsset('since-new.png', 'image/png');

		$result = $this->getJsonResponse($this->makeRequest('GET', '/media/library', [
			'query' => ['since' => '2021-01-01T00:00:00+00:00'],
		]));

		$uids = array_column($result['assets'], 'uid');
		$this->assertContains($fresh, $uids);
		$this->assertNotContains($old, $uids);
		$this->assertSame(1, $result['total']);
		$this->assertSame(1, $result['counts']['image']);

		// Invalid input means no cutoff rather than an error.
		$loose = $this->getJsonResponse($this->makeRequest('GET', '/media/library', [
			'query' => ['since' => 'not-a-date'],
		]));
		$this->assertSame(2, $loose['total']);
	}

	public function testLibraryRequiresAuthentication(): void
	{
		$response = $this->makeRequest('GET', '/media/library', ['authToken' => '']);

		$this->assertResponseStatus(401, $response);
	}

	/**
	 * Insert a catalog row directly: the kind filters classify by mime
	 * prefix in SQL, so pinning them must not depend on what libmagic
	 * detects for handcrafted upload bytes.
	 */
	private function insertAsset(string $filename, ?string $mime, ?string $created = null): string
	{
		$uid = bin2hex(random_bytes(8));
		$db = $this->db();
		$system = $db->execute("SELECT usr FROM cms.users WHERE rolename = 'system' LIMIT 1")->one();
		$this->assertNotEmpty($system);

		$db->execute(
			"INSERT INTO cms.assets (uid, disk, key, filename, mime, bytes, meta, creator, created)
			VALUES (:uid, 'local', :key, :filename, :mime, 1, '{}'::jsonb, :creator, :created)",
			[
				'uid' => $uid,
				'key' => "test/{$uid}/{$filename}",
				'filename' => $filename,
				'mime' => $mime,
				'creator' => (int) $system['usr'],
				'created' => $created ?? date(DATE_ATOM),
			],
		)->run();

		return $uid;
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
