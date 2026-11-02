<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Assets\Format;
use Cosray\Assets\ResizeMode;
use Cosray\Assets\Sizes;
use Cosray\Exception\RuntimeException;
use Cosray\Tests\TestCase;

/**
 * @internal
 *
 * @covers \Cosray\Assets\SizeSpec
 * @covers \Cosray\Assets\Sizes
 */
final class SizesTest extends TestCase
{
	public function testParsesEveryMode(): void
	{
		$sizes = new Sizes([
			'hero' => ['width' => 1920],
			'logo' => ['height' => 60],
			'strip' => ['long-side' => 800],
			'chip' => ['short-side' => 240],
			'card' => ['crop' => [600, 400]],
			'stage' => ['fit' => [1333, 1000]],
			'social' => ['resize' => [1200, 630]],
		]);

		$this->assertSame(ResizeMode::Width, $sizes->get('hero')->mode);
		$this->assertSame(1920, $sizes->get('hero')->first);
		$this->assertSame(ResizeMode::Height, $sizes->get('logo')->mode);
		$this->assertSame(ResizeMode::LongSide, $sizes->get('strip')->mode);
		$this->assertSame(ResizeMode::ShortSide, $sizes->get('chip')->mode);
		$this->assertSame(ResizeMode::Crop, $sizes->get('card')->mode);
		$this->assertSame([600, 400], [$sizes->get('card')->first, $sizes->get('card')->second]);
		$this->assertSame(ResizeMode::Fit, $sizes->get('stage')->mode);
		$this->assertSame(ResizeMode::Resize, $sizes->get('social')->mode);
	}

	public function testOptions(): void
	{
		$sizes = new Sizes([
			'thumb' => ['crop' => [400, 400], 'pos' => 'top', 'quality' => 75],
			'big' => ['width' => 3000, 'enlarge' => true],
		]);

		$this->assertSame(0.0, $sizes->get('thumb')->pos);
		$this->assertSame(75, $sizes->get('thumb')->quality);
		$this->assertTrue($sizes->get('big')->enlarge);
		$this->assertFalse($sizes->get('thumb')->enlarge);
		$this->assertNull($sizes->get('big')->quality);
	}

	public function testAFormatIsOneOfTheKnownOnes(): void
	{
		$sizes = new Sizes(['small' => ['width' => 400, 'format' => 'webp'], 'plain' => ['width' => 400]]);

		$this->assertSame(Format::Webp, $sizes->get('small')->format);
		$this->assertNull($sizes->get('plain')->format);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('`format` must be one of jpeg, png, webp, avif');

		new Sizes(['small' => ['width' => 400, 'format' => 'bmp']]);
	}

	public function testAChangedDefinitionHasAnotherFingerprint(): void
	{
		$fingerprint = static fn(array $entry): string => new Sizes(['card' => $entry])->get('card')->fingerprint();
		$base = $fingerprint(['crop' => [600, 400]]);

		$this->assertSame($base, $fingerprint(['crop' => [600, 400]]));
		$this->assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $base);

		foreach ([
			['crop' => [601, 400]],
			['crop' => [600, 401]],
			['fit' => [600, 400]],
			['crop' => [600, 400], 'pos' => 'top'],
			['crop' => [600, 400], 'quality' => 80],
			['crop' => [600, 400], 'enlarge' => true],
			['crop' => [600, 400], 'format' => 'webp'],
		] as $changed) {
			$this->assertNotSame($base, $fingerprint($changed), (string) json_encode($changed));
		}
	}

	public function testCropDefaultsToCenter(): void
	{
		$sizes = new Sizes(['card' => ['crop' => [600, 400]]]);

		$this->assertSame(0.5, $sizes->get('card')->pos);
	}

	public function testBuiltinsAlwaysAvailableButOverridable(): void
	{
		$sizes = new Sizes([]);
		$this->assertTrue($sizes->has('thumb'));
		$this->assertTrue($sizes->has('preview'));
		$this->assertSame(400, $sizes->get('thumb')->first);
		$this->assertSame(480, $sizes->get('block-sm')->first);
		$this->assertSame(960, $sizes->get('block')->first);
		$this->assertSame(1440, $sizes->get('block-lg')->first);
		// Width, not a crop: the gallery's ratio and crop settings shape the tiles.
		$this->assertSame(ResizeMode::Width, $sizes->get('block-thumb')->mode);
		$this->assertSame(480, $sizes->get('block-thumb')->first);

		$custom = new Sizes(['thumb' => ['crop' => [50, 50]]]);
		$this->assertSame(ResizeMode::Crop, $custom->get('thumb')->mode);
	}

	public function testUnknownNameThrows(): void
	{
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage("Unknown media size 'nope'");

		new Sizes([])->get('nope');
	}

	public function testInvalidEntriesThrowOnConstruction(): void
	{
		$invalid = [
			'two modes' => ['a' => ['width' => 1, 'height' => 2]],
			'no mode' => ['a' => ['quality' => 80]],
			'bad name' => ['Bad_Name' => ['width' => 100]],
			'bad dimension' => ['a' => ['width' => 0]],
			'bad pair' => ['a' => ['crop' => [600]]],
			'unknown key' => ['a' => ['width' => 100, 'wat' => true]],
			'pos without crop' => ['a' => ['width' => 100, 'pos' => 'top']],
			'bad pos' => ['a' => ['crop' => [1, 1], 'pos' => 'middle']],
			'bad quality' => ['a' => ['width' => 100, 'quality' => 101]],
			'bad enlarge' => ['a' => ['width' => 100, 'enlarge' => 1]],
			'not an array' => ['a' => 'width'],
		];

		foreach ($invalid as $case => $config) {
			try {
				new Sizes($config);
				$this->fail("Expected exception for: {$case}");
			} catch (RuntimeException $e) {
				$this->assertStringContainsString('media size', $e->getMessage(), $case);
			}
		}
	}
}
