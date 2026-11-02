<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Assets\Location;
use Cosray\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * @internal
 *
 * @covers \Cosray\Assets\Location
 */
final class LocationTest extends TestCase
{
	private string $file = '';

	protected function setUp(): void
	{
		parent::setUp();

		$this->file = sys_get_temp_dir() . '/cosray-location-' . bin2hex(random_bytes(6)) . '.jpg';
	}

	protected function tearDown(): void
	{
		if (is_file($this->file)) {
			unlink($this->file);
		}

		parent::tearDown();
	}

	/**
	 * A TIFF block with an orientation, a pointer to a GPS block with a latitude,
	 * and a software name behind it.
	 */
	private static function tiff(bool $little = true): string
	{
		$short = $little ? 'v' : 'n';
		$long = $little ? 'V' : 'N';
		$first = 8;
		$entries = 3;
		$software = $first + 2 + ($entries * 12) + 4;
		$gps = $software + 8;
		$latitude = $gps + 2 + 12 + 4;

		return (
			($little ? "II*\0" : "MM\0*")
				. pack($long, $first)
				. pack($short, $entries)
				. pack($short, 0x0112)
				. pack($short, 3)
				. pack($long, 1)
				. pack($short, 6)
				. pack($short, 0)
				. pack($short, 0x0131)
				. pack($short, 2)
				. pack($long, 8)
				. pack($long, $software)
				. pack($short, 0x8825)
				. pack($short, 4)
				. pack($long, 1)
				. pack($long, $gps)
				. pack($long, 0)
				. "Camera\0\0"
				. pack($short, 1)
				. pack($short, 2)
				. pack($short, 5)
				. pack($long, 3)
				. pack($long, $latitude)
				. pack($long, 0)
				. pack($long, 49)
				. pack($long, 1)
				. pack($long, 53)
				. pack($long, 1)
				. pack($long, 7)
				. pack($long, 1)
		);
	}

	private static function jpeg(string $tiff, string $more = ''): string
	{
		ob_start();
		imagejpeg(imagecreatetruecolor(4, 2));
		$jpeg = (string) ob_get_clean();
		$exif = "Exif\0\0{$tiff}";

		return substr($jpeg, 0, 2) . "\xFF\xE1" . pack('n', strlen($exif) + 2) . $exif . $more . substr($jpeg, 2);
	}

	/** @return array<string, mixed> */
	private function exif(string $jpeg): array
	{
		file_put_contents($this->file, $jpeg);

		return (array) exif_read_data($this->file);
	}

	/** @return iterable<string, array{bool}> */
	public static function byteOrders(): iterable
	{
		yield 'little endian' => [true];
		yield 'big endian' => [false];
	}

	#[DataProvider('byteOrders')]
	public function testTheGpsBlockGoesAndTheRestStays(bool $little): void
	{
		$jpeg = self::jpeg(self::tiff($little));
		$this->assertArrayHasKey('GPSLatitude', $this->exif($jpeg));

		$clean = Location::removed($jpeg, 'image/jpeg');
		$exif = $this->exif($clean);

		$this->assertArrayNotHasKey('GPSLatitude', $exif);
		$this->assertSame(6, $exif['Orientation']);
		$this->assertSame('Camera', $exif['Software']);
		$this->assertSame(strlen($jpeg), strlen($clean));
		$this->assertSame([4, 2], array_slice((array) getimagesizefromstring($clean), 0, 2));
	}

	public function testTheCoordinatesAreGoneFromTheBytes(): void
	{
		$latitude = pack('V', 49) . pack('V', 1) . pack('V', 53);
		$jpeg = self::jpeg(self::tiff());
		$this->assertStringContainsString($latitude, $jpeg);

		$this->assertStringNotContainsString($latitude, Location::removed($jpeg, 'image/jpeg'));
	}

	public function testAFileWithoutLocationStaysAsItIs(): void
	{
		$tiff = "II*\0" . pack('V', 8) . pack('v', 1) . pack('vvVvv', 0x0112, 3, 1, 6, 0) . pack('V', 0);
		$jpeg = self::jpeg($tiff);

		$this->assertSame($jpeg, Location::removed($jpeg, 'image/jpeg'));
	}

	public function testAnOtherFormatStaysAsItIs(): void
	{
		$svg = '<svg xmlns:exif="x" exif:GPSLatitude="1"/>';

		$this->assertSame($svg, Location::removed($svg, 'image/svg+xml'));
	}

	/** @return iterable<string, array{string}> */
	public static function damaged(): iterable
	{
		yield 'directory behind the end' => ["II*\0" . pack('V', 4000)];
		yield 'directory too long' => ["II*\0" . pack('V', 8) . pack('v', 500)];
		yield 'header alone' => ["MM\0*"];
		yield 'GPS block behind the end' => [
			"II*\0" . pack('V', 8) . pack('v', 1) . pack('vvVV', 0x8825, 4, 1, 90000) . pack('V', 0),
		];
	}

	#[DataProvider('damaged')]
	public function testDamagedMetadataDoesNotBreakTheFile(string $tiff): void
	{
		$jpeg = self::jpeg($tiff);
		$clean = Location::removed($jpeg, 'image/jpeg');

		$this->assertSame(strlen($jpeg), strlen($clean));
		$this->assertNotFalse(getimagesizefromstring($clean));
	}

	public function testXmpLosesItsGpsPropertiesInBothForms(): void
	{
		$xmp =
			'<rdf:Description exif:GPSLatitude="49,53.1N" drone-dji:GpsLongitude=\'10.9\' dc:title="Kept">'
			. '<exif:GPSAltitude>240/1</exif:GPSAltitude><dc:rights>Kept too</dc:rights></rdf:Description>';
		$jpeg = self::jpeg(self::tiff(), "\xFF\xE1" . pack('n', strlen($xmp) + 2) . $xmp);

		$clean = Location::removed($jpeg, 'image/jpeg');

		$this->assertSame(strlen($jpeg), strlen($clean));
		$this->assertStringNotContainsString('49,53.1N', $clean);
		$this->assertStringNotContainsString('10.9', $clean);
		$this->assertStringNotContainsString('240/1', $clean);
		$this->assertStringContainsString('dc:title="Kept"', $clean);
		$this->assertStringContainsString('<dc:rights>Kept too</dc:rights>', $clean);
	}

	public function testWebpLosesItsExifChunk(): void
	{
		ob_start();
		imagewebp(imagecreatetruecolor(4, 2));
		$webp = (string) ob_get_clean();
		$tiff = self::tiff();
		$file = $webp . 'EXIF' . pack('V', strlen($tiff)) . $tiff;

		$clean = Location::removed($file, 'image/webp');

		$this->assertSame(strlen($file), strlen($clean));
		$this->assertStringNotContainsString(pack('V', 49) . pack('V', 1), $clean);
		$this->assertStringStartsWith($webp, $clean);
	}

	public function testAPngLosesItAndKeepsValidChecksums(): void
	{
		ob_start();
		imagepng(imagecreatetruecolor(4, 2));
		$png = (string) ob_get_clean();
		$chunk = static fn(string $type, string $body): string => (
			pack('N', strlen($body)) . $type . $body . pack('N', crc32($type . $body))
		);
		$xmp = "XML:com.adobe.xmp\0\0\0\0\0" . '<x exif:GPSLatitude="49,53.1N" dc:title="Kept"/>';
		$file = substr($png, 0, 33) . $chunk('eXIf', self::tiff()) . $chunk('iTXt', $xmp) . substr($png, 33);

		$clean = Location::removed($file, 'image/png');

		$this->assertSame(strlen($file), strlen($clean));
		$this->assertStringNotContainsString('49,53.1N', $clean);
		$this->assertStringNotContainsString(pack('V', 49) . pack('V', 1), $clean);
		$this->assertStringContainsString('dc:title="Kept"', $clean);
		$this->assertAllChecksumsHold($clean);
		$this->assertNotFalse(imagecreatefromstring($clean));
	}

	private function assertAllChecksumsHold(string $png): void
	{
		$at = 8;

		while ($at < strlen($png)) {
			$length = (int) unpack('N', substr($png, $at, 4))[1];
			$crc = (int) unpack('N', substr($png, $at + 8 + $length, 4))[1];
			$this->assertSame(crc32(substr($png, $at + 4, 4 + $length)), $crc);
			$at += 12 + $length;
		}
	}
}
