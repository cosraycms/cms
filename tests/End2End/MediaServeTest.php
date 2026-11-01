<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Cosray\Assets\Sizes;
use Cosray\Assets\SizeSpec;
use Cosray\Assets\Transform;
use Cosray\Tests\End2EndTestCase;
use GdImage;
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
	private const string SECRET = 'test-secret';
	private const int RED = 0xFF0000;
	private const int GREEN = 0x00FF00;
	private const int BLUE = 0x0000FF;

	private string $publicDir;

	protected function setUp(): void
	{
		parent::setUp();
		$this->publicDir = sys_get_temp_dir() . '/cosray-serve-' . bin2hex(random_bytes(4));
		mkdir("{$this->publicDir}/assets", 0o755, true);
		mkdir("{$this->publicDir}/cache", 0o755, true);
		$this->app = $this->createApp([
			'path.public' => $this->publicDir,
			'app.secret' => self::SECRET,
			'media.sizes' => ['square' => ['crop' => [2, 2]], 'wide' => ['crop' => [8, 4]]],
		]);
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

	public function testASignedCropRendersOnlyThatPartOfTheImage(): void
	{
		$upload = $this->upload($this->quarters(), 'e2e-serve-crop.png');
		$transform = new Transform(['x' => 0.5, 'y' => 0, 'w' => 0.5, 'h' => 0.5]);

		$image = $this->fetched($upload, Sizes::original(), $transform);

		$this->assertSame([10, 10], [imagesx($image), imagesy($image)]);
		$this->assertSame(self::GREEN, $this->colorAt($image, 2, 2));
		$this->assertSame(self::GREEN, $this->colorAt($image, 8, 8));
	}

	public function testACropAppliesBeforeTheSize(): void
	{
		$upload = $this->upload($this->quarters(), 'e2e-serve-crop-size.png');
		$transform = new Transform(['x' => 0, 'y' => 0.5, 'w' => 0.5, 'h' => 0.5]);

		$image = $this->fetched($upload, new Sizes(['square' => ['crop' => [2, 2]]])->get('square'), $transform);

		$this->assertSame([2, 2], [imagesx($image), imagesy($image)]);
		$this->assertSame(self::BLUE, $this->colorAt($image, 1, 1));
	}

	public function testAFocalPointMovesTheCutOfASize(): void
	{
		$upload = $this->upload($this->quarters(40, 10), 'e2e-serve-focal.png');
		$spec = new Sizes(['square' => ['crop' => [2, 2]]])->get('square');

		$left = $this->fetched($upload, $spec, new Transform(null, ['x' => 0, 'y' => 0]));
		$right = $this->fetched($upload, $spec, new Transform(null, ['x' => 1, 'y' => 0]));

		$this->assertSame(self::RED, $this->colorAt($left, 1, 0));
		$this->assertSame(self::GREEN, $this->colorAt($right, 1, 0));
	}

	public function testACropFollowsARotatedJpeg(): void
	{
		if (!function_exists('exif_read_data')) {
			$this->markTestSkipped('The exif extension reads the orientation.');
		}

		$upload = $this->upload($this->rotatedJpeg(), 'e2e-serve-rotated.jpg', 'image/jpeg');
		$transform = new Transform(['x' => 0, 'y' => 0, 'w' => 1, 'h' => 0.5]);

		$image = $this->fetched($upload, Sizes::original(), $transform);

		// 40 wide and 20 high as stored, turned upright to 20 by 40.
		$this->assertSame([20, 20], [imagesx($image), imagesy($image)]);
		[$red, $green, $blue] = array_values(imagecolorsforindex($image, imagecolorat($image, 10, 10)));
		$this->assertGreaterThan(200, $red);
		$this->assertLessThan(60, $green);
		$this->assertLessThan(60, $blue);
	}

	public function testAVariantWithoutItsSignatureIsAMiss(): void
	{
		$upload = $this->upload($this->png(4, 4), 'e2e-serve-forged.png');
		$uid = (string) $upload['uid'];
		$dir = '/cache/' . substr($uid, 0, 2) . "/{$uid}";
		$variant = new Transform(['x' => 0, 'y' => 0, 'w' => 0.5, 'h' => 0.5])
			->variant($uid, Sizes::original(), self::SECRET);
		[, $token] = explode('~', $variant);

		$forged = [
			"e2e-serve-forged-original~{$token}~0000000000000000.png",
			"e2e-serve-forged-original~{$token}.png",
			"e2e-serve-forged-unknown{$variant}.png",
			'e2e-serve-forged-original.png',
		];

		foreach ($forged as $file) {
			$this->assertResponseStatus(404, $this->makeRequest('GET', "{$dir}/{$file}"));
			$this->assertFileDoesNotExist("{$this->publicDir}{$dir}/{$file}");
		}
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

	public function testOriginalTooLargeForTheMemoryLimitIsRedirectedTo(): void
	{
		$upload = $this->upload($this->png(4, 4), 'e2e-serve-large.png');
		$shard = substr((string) $upload['uid'], 0, 2);
		file_put_contents($this->publicDir . $upload['url'], $this->png(4000, 4000));
		$limit = (string) ini_get('memory_limit');
		// @mago-expect lint:no-ini-set
		ini_set('memory_limit', (string) (memory_get_usage(true) + (32 * 1024 * 1024)));

		try {
			$response = $this->makeRequest(
				'GET',
				"/cache/{$shard}/{$upload['uid']}/e2e-serve-large-square.png",
			);
		} finally {
			// @mago-expect lint:no-ini-set
			ini_set('memory_limit', $limit);
		}

		$this->assertResponseStatus(302, $response);
		$this->assertResponseHeaderEquals('Location', $upload['url'], $response);
	}

	/** @param array<string, mixed> $upload */
	private function fetched(array $upload, SizeSpec $spec, Transform $transform): GdImage
	{
		$uid = (string) $upload['uid'];
		$stem = pathinfo((string) $upload['url'], PATHINFO_FILENAME);
		$ext = pathinfo((string) $upload['url'], PATHINFO_EXTENSION);
		$variant = $transform->variant($uid, $spec, self::SECRET);
		$url = '/cache/' . substr($uid, 0, 2) . "/{$uid}/{$stem}-{$spec->name}{$variant}.{$ext}";
		$response = $this->makeRequest('GET', $url);
		$this->assertResponseOk($response);
		$this->assertFileExists($this->publicDir . $url);

		$image = imagecreatefromstring((string) $response->getBody());
		$this->assertInstanceOf(GdImage::class, $image);

		return $image;
	}

	private function colorAt(GdImage $image, int $x, int $y): int
	{
		return imagecolorat($image, $x, $y) & 0xFFFFFF;
	}

	/** Red, green, blue and white quarters, clockwise from the top left. */
	private function quarters(int $width = 20, int $height = 20): string
	{
		$image = imagecreatetruecolor($width, $height);
		$halfX = intdiv($width, 2);
		$halfY = intdiv($height, 2);
		imagefilledrectangle($image, 0, 0, $width, $height, self::RED);
		imagefilledrectangle($image, $halfX, 0, $width, $height, self::GREEN);
		imagefilledrectangle($image, 0, $halfY, $halfX - 1, $height, self::BLUE);
		imagefilledrectangle($image, $halfX, $halfY, $width, $height, 0xFFFFFF);
		ob_start();
		imagepng($image);

		return (string) ob_get_clean();
	}

	/** Red on the left, blue on the right, with the orientation that turns it a quarter clockwise. */
	private function rotatedJpeg(): string
	{
		$image = imagecreatetruecolor(40, 20);
		imagefilledrectangle($image, 0, 0, 20, 20, self::RED);
		imagefilledrectangle($image, 20, 0, 40, 20, self::BLUE);
		ob_start();
		imagejpeg($image, null, 100);
		$jpeg = (string) ob_get_clean();
		// A big-endian TIFF block with one entry, tag 0x0112 Orientation, of type SHORT and value 6.
		$tiff = "MM\x00\x2A\x00\x00\x00\x08\x00\x01\x01\x12\x00\x03\x00\x00\x00\x01\x00\x06\x00\x00\x00\x00\x00\x00\x00\x00";
		$app1 = "Exif\x00\x00{$tiff}";

		return substr($jpeg, 0, 2) . "\xFF\xE1" . pack('n', strlen($app1) + 2) . $app1 . substr($jpeg, 2);
	}

	private function png(int $width, int $height): string
	{
		$image = imagecreatetruecolor($width, $height);
		ob_start();
		imagepng($image);

		return (string) ob_get_clean();
	}

	private function upload(string $contents, string $filename, string $mediaType = 'image/png'): array
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
