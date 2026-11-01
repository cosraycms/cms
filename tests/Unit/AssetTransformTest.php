<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Assets\ResizeMode;
use Cosray\Assets\SizeSpec;
use Cosray\Assets\Transform;
use Cosray\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * @internal
 *
 * @coversNothing
 */
final class AssetTransformTest extends TestCase
{
	private const string SECRET = 'test-secret';
	private const array CROP = ['x' => 0.1, 'y' => 0.2, 'w' => 0.5, 'h' => 0.6];
	private const array FOCAL = ['x' => 0.3, 'y' => 0.4];

	/** @return iterable<string, array{array<string, mixed>}> */
	public static function geometry(): iterable
	{
		$cases = json_decode((string) file_get_contents(__DIR__ . '/../../contract/image-geometry.json'), true);

		foreach ($cases as $case) {
			yield $case['name'] => [$case];
		}
	}

	#[DataProvider('geometry')]
	public function testSelectsTheSharedRegion(array $case): void
	{
		$transform = new Transform($case['crop'] ?? null, $case['focal'] ?? null);

		$this->assertSame($case['region'], $transform->region($case['width'], $case['height'], $case['ratio'] ?? null));
	}

	public function testHoldsNothingWithoutUsableValues(): void
	{
		$this->assertNull(Transform::from(null, null));
		$this->assertNull(Transform::from(['x' => 0, 'y' => 0, 'w' => 1, 'h' => 1], 'nope'));
		$this->assertNull(Transform::from(['x' => 0.9, 'y' => 0, 'w' => 0.5, 'h' => 1], null));
		$this->assertNull(Transform::from(['x' => 0, 'y' => 0, 'w' => 0.001, 'h' => 1], null));
		$this->assertNull(Transform::from(['x' => 0, 'y' => 0, 'w' => 'wide', 'h' => 1], null));
	}

	public function testClampsTheFocalPoint(): void
	{
		$this->assertSame(['x' => 1.0, 'y' => 0.0], Transform::from(null, ['x' => 3, 'y' => -1])?->focal);
	}

	public function testAcceptsTheFullImageAsACropThatAsksForNothing(): void
	{
		$this->assertTrue(Transform::validCrop(['x' => 0, 'y' => 0, 'w' => 1, 'h' => 1]));
		$this->assertTrue(Transform::validCrop(self::CROP));
		$this->assertFalse(Transform::validCrop(['x' => 0.9, 'y' => 0, 'w' => 0.5, 'h' => 1]));
		$this->assertFalse(Transform::validCrop(null));
	}

	public function testMapsTheFocalPointIntoTheCrop(): void
	{
		$focal = new Transform(self::CROP, self::FOCAL)->focalInCrop();

		$this->assertEqualsWithDelta(0.4, $focal['x'], 0.0001);
		$this->assertEqualsWithDelta(1 / 3, $focal['y'], 0.0001);
	}

	public function testAFocalPointOnlyChangesASizeThatCuts(): void
	{
		$focal = new Transform(null, self::FOCAL);

		$this->assertSame('', $focal->variant('uid', $this->spec(ResizeMode::Width), self::SECRET));
		$this->assertStringStartsWith('~f3000.4000~', $focal->variant(
			'uid',
			$this->spec(ResizeMode::Crop),
			self::SECRET,
		));
	}

	public function testACropChangesEverySize(): void
	{
		$transform = new Transform(self::CROP, self::FOCAL);

		$this->assertStringStartsWith(
			'~c1000.2000.5000.6000~',
			$transform->variant('uid', $this->spec(ResizeMode::Width), self::SECRET),
		);
		$this->assertStringStartsWith(
			'~c1000.2000.5000.6000_f3000.4000~',
			$transform->variant('uid', $this->spec(ResizeMode::Crop), self::SECRET),
		);
	}

	public function testReadsOnlyItsOwnSignature(): void
	{
		$variant = new Transform(self::CROP, self::FOCAL)->variant('uid', $this->spec(ResizeMode::Crop), self::SECRET);
		[, $token, $signature] = explode('~', $variant);

		$read = Transform::verified('uid', 'card', $token, $signature, self::SECRET);
		$this->assertEqualsWithDelta(0.1, $read?->crop['x'], 0.0001);
		$this->assertEqualsWithDelta(0.4, $read?->focal['y'], 0.0001);

		$this->assertNull(Transform::verified('other', 'card', $token, $signature, self::SECRET));
		$this->assertNull(Transform::verified('uid', 'thumb', $token, $signature, self::SECRET));
		$this->assertNull(Transform::verified('uid', 'card', 'c0.0.5000.5000', $signature, self::SECRET));
		$this->assertNull(Transform::verified('uid', 'card', $token, $signature, 'another-secret'));
	}

	private function spec(ResizeMode $mode): SizeSpec
	{
		return new SizeSpec('card', $mode, 100, 100);
	}
}
