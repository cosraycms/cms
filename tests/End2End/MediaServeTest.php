<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Cosray\Assets\Sizes;
use Cosray\Assets\SizeSpec;
use Cosray\Assets\Transform;
use Cosray\Tests\End2EndTestCase;
use GdImage;
use PHPUnit\Framework\Attributes\DataProvider;
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
	private const int WHITE = 0xFFFFFF;
	private const array SIZES = [
		'square' => ['crop' => [2, 2]],
		'wide' => ['crop' => [8, 4]],
		'small' => ['width' => 16],
		'as-webp' => ['width' => 16, 'format' => 'webp'],
		'as-jpeg' => ['width' => 16, 'format' => 'jpeg'],
		'as-avif' => ['width' => 16, 'format' => 'avif'],
	];

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
			'media.sizes' => self::SIZES,
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
		$url = $this->namedUrl($upload, 'square');

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

		$original = Sizes::original()->fingerprint();
		$forged = [
			"e2e-serve-forged-original~{$original}~{$token}~0000000000000000.png",
			"e2e-serve-forged-original~{$original}~{$token}.png",
			"e2e-serve-forged-unknown~{$original}{$variant}.png",
			"e2e-serve-forged-original~00000000{$variant}.png",
			"e2e-serve-forged-original~{$original}.png",
		];

		foreach ($forged as $file) {
			$this->assertResponseStatus(404, $this->makeRequest('GET', "{$dir}/{$file}"));
			$this->assertFileDoesNotExist("{$this->publicDir}{$dir}/{$file}");
		}
	}

	public function testANameOfAnotherDefinitionIsAMiss(): void
	{
		$upload = $this->upload($this->png(4, 4), 'e2e-serve-older.png');
		$current = $this->namedUrl($upload, 'small');
		$older = str_replace(new Sizes(self::SIZES)->get('small')->fingerprint(), '00000000', $current);
		$withoutOne = self::withoutFingerprints($current);

		$this->assertResponseStatus(404, $this->makeRequest('GET', $older));
		$this->assertResponseStatus(404, $this->makeRequest('GET', $withoutOne));
		$this->assertResponseOk($this->makeRequest('GET', $current));
	}

	public function testASizeCanTurnAnImageIntoWebp(): void
	{
		$upload = $this->upload($this->quarters(), 'e2e-serve-to-webp.png');
		$url = $this->namedUrl($upload, 'as-webp', 'webp');

		$response = $this->makeRequest('GET', $url);

		$this->assertResponseOk($response);
		$this->assertSame(IMAGETYPE_WEBP, getimagesizefromstring((string) $response->getBody())[2]);
		$this->assertSame('image/webp', $response->getHeaderLine('Content-Type'));
		$this->assertFileExists($this->publicDir . $url);
	}

	public function testATransparentImageTurnedIntoJpegSitsOnWhite(): void
	{
		$image = imagecreatetruecolor(40, 20);
		imagealphablending($image, false);
		imagesavealpha($image, true);
		imagefilledrectangle($image, 0, 0, 39, 19, imagecolorallocatealpha($image, 0, 0, 0, 127));
		ob_start();
		imagepng($image);
		$upload = $this->upload((string) ob_get_clean(), 'e2e-serve-to-jpeg.png');

		$response = $this->makeRequest('GET', $this->namedUrl($upload, 'as-jpeg', 'jpg'));

		$this->assertResponseOk($response);
		$this->assertSame(IMAGETYPE_JPEG, getimagesizefromstring((string) $response->getBody())[2]);
		$this->assertSame(self::WHITE, $this->nearest(imagecreatefromstring((string) $response->getBody()), 8, 4));
	}

	public function testAGifStaysAGifWhateverTheSizeAsksFor(): void
	{
		ob_start();
		imagegif($this->paletted());
		$upload = $this->upload((string) ob_get_clean(), 'e2e-serve-gif-stays.gif', 'image/gif');

		$response = $this->makeRequest('GET', $this->namedUrl($upload, 'as-webp', 'gif'));

		$this->assertResponseOk($response);
		$this->assertSame(IMAGETYPE_GIF, getimagesizefromstring((string) $response->getBody())[2]);
	}

	public function testAvifIsReadAndWritten(): void
	{
		if (!function_exists('imageavif') || !(imagetypes() & IMG_AVIF)) {
			$this->markTestSkipped('This build of GD has no AVIF.');
		}

		ob_start();
		imageavif(imagecreatefromstring($this->quarters(40, 20)), null, 90);
		$upload = $this->upload((string) ob_get_clean(), 'e2e-serve-avif.avif', 'image/avif');

		$image = $this->named($upload, 'small');
		$this->assertSame([16, 8], [imagesx($image), imagesy($image)]);
		$this->assertSame([self::RED, self::GREEN, self::BLUE, self::WHITE], $this->corners($image));

		$png = $this->upload($this->quarters(40, 20), 'e2e-serve-to-avif.png');
		$response = $this->makeRequest('GET', $this->namedUrl($png, 'as-avif', 'avif'));
		$this->assertResponseOk($response);
		$this->assertSame(IMAGETYPE_AVIF, getimagesizefromstring((string) $response->getBody())[2]);
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
			"/cache/{$shard}/{$upload['uid']}/e2e-serve-pic-w9999~00000000.png",
		));
	}

	public function testStaleFilenameIs404(): void
	{
		$upload = $this->upload($this->png(4, 4), 'e2e-serve-pic.png');
		$url = str_replace('e2e-serve-pic-', 'renamed-', $this->namedUrl($upload, 'square'));

		$this->assertResponseStatus(404, $this->makeRequest('GET', $url));
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
		unlink($this->publicDir . $upload['url']);

		$this->assertResponseStatus(404, $this->makeRequest('GET', $this->namedUrl($upload, 'square')));
	}

	public function testMissingAssetsDirectoryIs404(): void
	{
		$upload = $this->upload($this->png(4, 4), 'e2e-serve-no-assets.png');
		$url = $this->namedUrl($upload, 'square');
		$this->removeDir("{$this->publicDir}/assets");

		$this->assertResponseStatus(404, $this->makeRequest('GET', $url));
	}

	public function testMissingCacheDirectoryIsCreatedForARendition(): void
	{
		$upload = $this->upload($this->png(4, 4), 'e2e-serve-no-cache.png');
		$url = $this->namedUrl($upload, 'square');
		$this->removeDir("{$this->publicDir}/cache");

		$this->assertResponseStatus(200, $this->makeRequest('GET', $url));
		$this->assertFileExists($this->publicDir . $url);
	}

	public function testOriginalTooLargeForTheMemoryLimitIsRedirectedTo(): void
	{
		$upload = $this->upload($this->png(4, 4), 'e2e-serve-large.png');
		file_put_contents($this->publicDir . $upload['url'], $this->png(4000, 4000));
		$limit = (string) ini_get('memory_limit');
		// @mago-expect lint:no-ini-set
		ini_set('memory_limit', (string) (memory_get_usage(true) + (32 * 1024 * 1024)));

		try {
			$response = $this->makeRequest('GET', $this->namedUrl($upload, 'square'));
		} finally {
			// @mago-expect lint:no-ini-set
			ini_set('memory_limit', $limit);
		}

		$this->assertResponseStatus(302, $response);
		$this->assertResponseHeaderEquals('Location', $upload['url'], $response);
	}

	/**
	 * The corners as a visitor sees them, top left to bottom right, of a stored
	 * image with red, green, blue and white quarters.
	 *
	 * @return iterable<string, array{int, list<int>}>
	 */
	public static function orientations(): iterable
	{
		[$r, $g, $b, $w] = [self::RED, self::GREEN, self::BLUE, self::WHITE];

		yield 'upright' => [1, [$r, $g, $b, $w]];
		yield 'mirrored' => [2, [$g, $r, $w, $b]];
		yield 'upside down' => [3, [$w, $b, $g, $r]];
		yield 'flipped' => [4, [$b, $w, $r, $g]];
		yield 'transposed' => [5, [$r, $b, $g, $w]];
		yield 'turned clockwise' => [6, [$b, $r, $w, $g]];
		yield 'transversed' => [7, [$w, $g, $b, $r]];
		yield 'turned anticlockwise' => [8, [$g, $w, $r, $b]];
	}

	/** @param list<int> $corners */
	#[DataProvider('orientations')]
	public function testARenditionShowsAJpegUpright(int $orientation, array $corners): void
	{
		$upload = $this->upload(
			$this->orientedJpeg($orientation),
			"e2e-serve-upright-{$orientation}.jpg",
			'image/jpeg',
		);
		$image = $this->named($upload, 'small');

		$this->assertSame($orientation > 4 ? [16, 32] : [16, 8], [imagesx($image), imagesy($image)]);
		$this->assertSame($corners, $this->corners($image));
	}

	/** @param list<int> $corners */
	#[DataProvider('orientations')]
	public function testACropIsReadOffAJpegAsItIsShown(int $orientation, array $corners): void
	{
		$upload = $this->upload($this->orientedJpeg($orientation), "e2e-serve-cut-{$orientation}.jpg", 'image/jpeg');

		foreach ([[0, 0], [0.5, 0], [0, 0.5], [0.5, 0.5]] as $at => [$x, $y]) {
			$image = $this->fetched($upload, Sizes::original(), new Transform([
				'x' => $x,
				'y' => $y,
				'w' => 0.5,
				'h' => 0.5,
			]));

			$this->assertSame($orientation > 4 ? [10, 20] : [20, 10], [imagesx($image), imagesy($image)]);
			$this->assertSame(array_fill(0, 4, $corners[$at]), $this->corners($image));
		}
	}

	public function testATransparentPngStaysTransparent(): void
	{
		$image = imagecreatetruecolor(40, 20);
		imagealphablending($image, false);
		imagesavealpha($image, true);
		imagefilledrectangle($image, 0, 0, 19, 19, imagecolorallocatealpha($image, 0, 0, 0, 127));
		imagefilledrectangle($image, 20, 0, 39, 19, self::RED);
		ob_start();
		imagepng($image);

		$this->assertHalfTransparent($this->named(
			$this->upload((string) ob_get_clean(), 'e2e-serve-alpha.png'),
			'small',
		));
	}

	public function testAPalettePngKeepsItsTransparentColor(): void
	{
		ob_start();
		imagepng($this->paletted());

		$this->assertHalfTransparent($this->named(
			$this->upload((string) ob_get_clean(), 'e2e-serve-palette.png'),
			'small',
		));
	}

	public function testAStillGifKeepsItsTransparentColor(): void
	{
		ob_start();
		imagegif($this->paletted());
		$upload = $this->upload((string) ob_get_clean(), 'e2e-serve-still.gif', 'image/gif');

		$this->assertHalfTransparent($this->named($upload, 'small'));
	}

	public function testATransparentWebpStaysTransparent(): void
	{
		$image = imagecreatetruecolor(40, 20);
		imagealphablending($image, false);
		imagesavealpha($image, true);
		imagefilledrectangle($image, 0, 0, 19, 19, imagecolorallocatealpha($image, 0, 0, 0, 127));
		imagefilledrectangle($image, 20, 0, 39, 19, self::RED);
		ob_start();
		imagewebp($image, null, 100);
		$upload = $this->upload((string) ob_get_clean(), 'e2e-serve-alpha.webp', 'image/webp');

		$this->assertHalfTransparent($this->named($upload, 'small'));
	}

	public function testAnAnimatedGifStaysACopyOfItsOriginal(): void
	{
		ob_start();
		imagegif($this->paletted());
		$gif = (string) ob_get_clean();
		$start = (int) strpos($gif, "\x21\xF9\x04");
		$frame = substr($gif, $start, -1);
		$animated = substr($gif, 0, $start) . $frame . $frame . $frame . "\x3B";
		$upload = $this->upload($animated, 'e2e-serve-moving.gif', 'image/gif');

		$response = $this->makeRequest('GET', $this->namedUrl($upload, 'small'));

		$this->assertResponseOk($response);
		$this->assertSame($animated, (string) $response->getBody());
	}

	public function testAnOriginalThatCannotBeReadIsRedirectedTo(): void
	{
		$upload = $this->upload($this->png(4, 4), 'e2e-serve-broken.png');
		file_put_contents($this->publicDir . $upload['url'], 'no image');

		$response = $this->makeRequest('GET', $this->namedUrl($upload, 'small'));

		$this->assertResponseStatus(302, $response);
		$this->assertResponseHeaderEquals('Location', $upload['url'], $response);
	}

	public function testAJpegThatBreaksOffIsRedirectedToOrRendersWhatIsThere(): void
	{
		$upload = $this->upload($this->orientedJpeg(1), 'e2e-serve-short.jpg', 'image/jpeg');
		$original = $this->publicDir . $upload['url'];
		file_put_contents($original, substr((string) file_get_contents($original), 0, 400));

		$response = $this->makeRequest('GET', $this->namedUrl($upload, 'small'));

		$this->assertContains($response->getStatusCode(), [200, 302]);
	}

	/** @param array<string, mixed> $upload */
	private function fetched(array $upload, SizeSpec $spec, Transform $transform): GdImage
	{
		$uid = (string) $upload['uid'];
		$stem = pathinfo((string) $upload['url'], PATHINFO_FILENAME);
		$ext = pathinfo((string) $upload['url'], PATHINFO_EXTENSION);
		$variant = $transform->variant($uid, $spec, self::SECRET);
		$url = '/cache/' . substr($uid, 0, 2) . "/{$uid}/{$stem}-{$spec->name}~{$spec->fingerprint()}{$variant}.{$ext}";
		$response = $this->makeRequest('GET', $url);
		$this->assertResponseOk($response);
		$this->assertFileExists($this->publicDir . $url);

		$image = imagecreatefromstring((string) $response->getBody());
		$this->assertInstanceOf(GdImage::class, $image);

		return $image;
	}

	/** @param array<string, mixed> $upload */
	private function named(array $upload, string $size): GdImage
	{
		$response = $this->makeRequest('GET', $this->namedUrl($upload, $size));
		$this->assertResponseOk($response);

		$image = imagecreatefromstring((string) $response->getBody());
		$this->assertInstanceOf(GdImage::class, $image);

		return $image;
	}

	/** @param array<string, mixed> $upload */
	private function namedUrl(array $upload, string $size, ?string $ext = null): string
	{
		$uid = (string) $upload['uid'];
		$stem = pathinfo((string) $upload['url'], PATHINFO_FILENAME);
		$ext ??= pathinfo((string) $upload['url'], PATHINFO_EXTENSION);

		$spec = new Sizes(self::SIZES)->get($size);

		return '/cache/' . substr($uid, 0, 2) . "/{$uid}/{$stem}-{$size}~{$spec->fingerprint()}.{$ext}";
	}

	/**
	 * The nearest of the test colors at each corner, top left to bottom right.
	 *
	 * @return list<int>
	 */
	private function corners(GdImage $image): array
	{
		$right = imagesx($image) - 2;
		$bottom = imagesy($image) - 2;

		return array_map(
			fn(array $at): int => $this->nearest($image, $at[0], $at[1]),
			[[1, 1], [$right, 1], [1, $bottom], [$right, $bottom]],
		);
	}

	private function nearest(GdImage $image, int $x, int $y): int
	{
		['red' => $red, 'green' => $green, 'blue' => $blue] = imagecolorsforindex($image, imagecolorat($image, $x, $y));
		$distance = static fn(int $color): int => (
			abs(($color >> 16) - $red) + abs((($color >> 8) & 0xFF) - $green) + abs(($color & 0xFF) - $blue)
		);
		$colors = [self::RED, self::GREEN, self::BLUE, self::WHITE];
		usort($colors, static fn(int $a, int $b): int => $distance($a) <=> $distance($b));

		return $colors[0];
	}

	/** Transparent on the left, opaque on the right. */
	private function assertHalfTransparent(GdImage $image): void
	{
		$right = imagesx($image) - 2;
		$middle = (int) (imagesy($image) / 2);

		$this->assertSame(127, imagecolorsforindex($image, imagecolorat($image, 1, $middle))['alpha']);
		$this->assertSame(0, imagecolorsforindex($image, imagecolorat($image, $right, $middle))['alpha']);
	}

	/** A palette image, transparent on the left and red on the right. */
	private function paletted(): GdImage
	{
		$image = imagecreate(40, 20);
		$clear = imagecolorallocate($image, 0, 255, 0);
		imagecolortransparent($image, $clear);
		imagefilledrectangle($image, 20, 0, 39, 19, imagecolorallocate($image, 255, 0, 0));

		return $image;
	}

	/** Red, green, blue and white quarters as stored, with the given EXIF orientation. */
	private function orientedJpeg(int $orientation): string
	{
		ob_start();
		imagejpeg(imagecreatefromstring($this->quarters(40, 20)), null, 100);
		$jpeg = (string) ob_get_clean();
		$tiff = "II*\0" . pack('V', 8) . pack('v', 1) . pack('vvVvv', 0x0112, 3, 1, $orientation, 0) . pack('V', 0);
		$app1 = "Exif\0\0{$tiff}";

		return substr($jpeg, 0, 2) . "\xFF\xE1" . pack('n', strlen($app1) + 2) . $app1 . substr($jpeg, 2);
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
		imagefilledrectangle($image, $halfX, $halfY, $width, $height, self::WHITE);
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
