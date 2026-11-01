<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Assets\Asset;
use Cosray\Context;
use Cosray\Field\Field;
use Cosray\Field\Image as ImageField;
use Cosray\Field\Services;
use Cosray\Node\FieldOwner;
use Cosray\Tests\TestCase;
use Cosray\Value\Image;
use Cosray\Value\ValueContext;
use SensitiveParameter;

/**
 * @internal
 *
 * @coversNothing
 */
final class ImageValueTest extends TestCase
{
	private const string UID = 'img0000000001';
	private const array CROP = ['x' => 0.1, 'y' => 0.2, 'w' => 0.5, 'h' => 0.5];

	public function testAPlainImageKeepsItsUrls(): void
	{
		$image = $this->image([]);

		$this->assertSame('/assets/im/' . self::UID . '/pic.jpg', $image->publicPath());
		$this->assertSame('/cache/im/' . self::UID . '/pic-square.jpg', $image->size('square')->publicPath());
		$this->assertNull($image->focal());
		$this->assertSame('', $image->position());
		$this->assertStringNotContainsString('style=', (string) $image->tag());
	}

	public function testACropIsSignedIntoEveryUrl(): void
	{
		$image = $this->image(['crop' => self::CROP]);

		$this->assertMatchesRegularExpression(
			'#^/cache/im/' . self::UID . '/pic-original~c1000\.2000\.5000\.5000~[0-9a-f]{16}\.jpg$#',
			$image->publicPath(),
		);
		$this->assertMatchesRegularExpression(
			'#/pic-thumb~c1000\.2000\.5000\.5000~[0-9a-f]{16}\.jpg$#',
			$image->size('thumb')->publicPath(),
		);
	}

	public function testAFocalPointOnlyRenamesASizeThatCuts(): void
	{
		$image = $this->image(['focal' => ['x' => 0.3, 'y' => 0.4]]);

		$this->assertSame('/assets/im/' . self::UID . '/pic.jpg', $image->publicPath());
		$this->assertSame('/cache/im/' . self::UID . '/pic-thumb.jpg', $image->size('thumb')->publicPath());
		$this->assertMatchesRegularExpression(
			'#/pic-square~f3000\.4000~[0-9a-f]{16}\.jpg$#',
			$image->size('square')->publicPath(),
		);
	}

	public function testAUsesFocalPointWinsOverTheCatalogs(): void
	{
		$catalog = ['focal' => ['x' => 0.1, 'y' => 0.2]];

		$this->assertSame(['x' => 0.1, 'y' => 0.2], $this->image([], $catalog)->focal());
		$this->assertSame(
			['x' => 0.3, 'y' => 0.4],
			$this->image(['focal' => ['x' => 0.3, 'y' => 0.4]], $catalog)->focal(),
		);
	}

	public function testTheFocalPointIsGivenInTheImageAVisitorGets(): void
	{
		$image = $this->image(['crop' => self::CROP, 'focal' => ['x' => 0.35, 'y' => 0.2]]);

		$this->assertEqualsWithDelta(0.5, $image->focal()['x'], 0.0001);
		$this->assertSame('object-position: 50% 0%', $image->position());
		$this->assertStringContainsString('style="object-position: 50% 0%"', (string) $image->tag());
	}

	public function testJsonDescribesTheCroppedImage(): void
	{
		$json = $this->image(['crop' => self::CROP, 'focal' => ['x' => 0.35, 'y' => 0.2]])->json();

		$this->assertSame(500, $json['width']);
		$this->assertSame(250, $json['height']);
		$this->assertStringContainsString('-original~', $json['url']);
		$this->assertStringContainsString('-square~c1000.2000.5000.5000_f3500.2000~', $json['sizes']['square']);
		$this->assertStringContainsString('-thumb~c1000.2000.5000.5000~', $json['sizes']['thumb']);
	}

	public function testWithoutASecretTheImageStaysWhole(): void
	{
		$image = $this->image(['crop' => self::CROP, 'focal' => ['x' => 0.35, 'y' => 0.2]], secret: null);

		$this->assertSame('/assets/im/' . self::UID . '/pic.jpg', $image->publicPath());
		$this->assertSame('/cache/im/' . self::UID . '/pic-square.jpg', $image->size('square')->publicPath());
		$this->assertSame(1000, $image->json()['width']);
		$this->assertSame(['x' => 0.35, 'y' => 0.2], $image->focal());
	}

	public function testAnSvgStaysItself(): void
	{
		$image = $this->image(['crop' => self::CROP, 'focal' => ['x' => 0.35, 'y' => 0.2]], mime: 'image/svg+xml');

		$this->assertSame('/assets/im/' . self::UID . '/pic.jpg', $image->size('square')->publicPath());
		$this->assertSame(['x' => 0.35, 'y' => 0.2], $image->focal());
	}

	/**
	 * @param array<string, mixed> $meta
	 * @param array<string, mixed> $catalog
	 */
	private function image(
		array $meta,
		array $catalog = [],
		#[SensitiveParameter]
		?string $secret = 'test-secret',
		string $mime = 'image/jpeg',
	): Image {
		$psrRequest = $this->psrRequest();
		$locales = new \Cosray\Locales();
		$locales->add('en', title: 'English', domains: ['www.example.com']);
		$psrRequest = $psrRequest
			->withAttribute('locales', $locales)
			->withAttribute('locale', $locales->get('en'))
			->withAttribute('defaultLocale', $locales->getDefault());
		$context = new Context(
			$this->db(),
			new \Celema\Core\Request($psrRequest),
			$this->config(['app.secret' => $secret, 'media.sizes' => ['square' => ['crop' => [300, 300]]]]),
			$this->container(),
			$this->factory(),
		);
		$context->assets()->add(new Asset(
			uid: self::UID,
			disk: 'local',
			key: 'im/' . self::UID . '/pic.jpg',
			filename: 'pic.jpg',
			mime: $mime,
			width: 1000,
			height: 500,
			meta: $catalog,
		));
		$data = ['value' => [Field::NEUTRAL_LOCALE => [['uid' => self::UID, 'meta' => $meta]]]];
		$field = new ImageField('image', new FieldOwner($context, 'test-node'), new ValueContext('image', $data));
		$field->init(Services::withDefaults());
		$field->limit(1);

		return $field->value();
	}
}
