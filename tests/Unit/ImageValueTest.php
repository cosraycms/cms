<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Closure;
use Cosray\Assets\Asset;
use Cosray\Context;
use Cosray\Field\Field;
use Cosray\Field\Image as ImageField;
use Cosray\Field\Services;
use Cosray\Markup;
use Cosray\Node\FieldOwner;
use Cosray\Tests\TestCase;
use Cosray\Value\Image;
use Cosray\Value\Images;
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
	private const array FILLED = [
		'caption' => ['zxx' => 'Words'],
		'attribution' => '© <Jane>',
		'link' => ['href' => '/about'],
	];

	public function testAPlainImageKeepsItsUrls(): void
	{
		$image = $this->image([]);

		$this->assertSame('/assets/im/' . self::UID . '/pic.jpg', $image->publicPath());
		$this->assertSame(
			'/cache/im/' . self::UID . '/pic-square.jpg',
			self::withoutFingerprints($image->size('square')->publicPath()),
		);
		$this->assertNull($image->focal());
		$this->assertSame('', $image->position());
		$this->assertStringNotContainsString('style=', (string) $image->img());
	}

	public function testACropIsSignedIntoEveryUrl(): void
	{
		$image = $this->image(['crop' => self::CROP]);

		$this->assertMatchesRegularExpression(
			'#^/cache/im/' . self::UID . '/pic-original~c1000\.2000\.5000\.5000~[0-9a-f]{16}\.jpg$#',
			self::withoutFingerprints($image->publicPath()),
		);
		$this->assertMatchesRegularExpression(
			'#/pic-thumb~c1000\.2000\.5000\.5000~[0-9a-f]{16}\.jpg$#',
			self::withoutFingerprints($image->size('thumb')->publicPath()),
		);
	}

	public function testAFocalPointOnlyRenamesASizeThatCuts(): void
	{
		$image = $this->image(['focal' => ['x' => 0.3, 'y' => 0.4]]);

		$this->assertSame('/assets/im/' . self::UID . '/pic.jpg', $image->publicPath());
		$this->assertSame(
			'/cache/im/' . self::UID . '/pic-thumb.jpg',
			self::withoutFingerprints($image->size('thumb')->publicPath()),
		);
		$this->assertMatchesRegularExpression(
			'#/pic-square~f3000\.4000~[0-9a-f]{16}\.jpg$#',
			self::withoutFingerprints($image->size('square')->publicPath()),
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
		$this->assertStringContainsString('style="object-position: 50% 0%"', (string) $image->img());
	}

	public function testJsonDescribesTheCroppedImage(): void
	{
		$json = $this->image(['crop' => self::CROP, 'focal' => ['x' => 0.35, 'y' => 0.2]])->json();

		$this->assertSame(500, $json['width']);
		$this->assertSame(250, $json['height']);
		$this->assertStringContainsString('-original~', $json['url']);
		$this->assertStringContainsString(
			'-square~c1000.2000.5000.5000_f3500.2000~',
			self::withoutFingerprints($json['sizes']['square']),
		);
		$this->assertStringContainsString(
			'-thumb~c1000.2000.5000.5000~',
			self::withoutFingerprints($json['sizes']['thumb']),
		);
	}

	public function testJsonGivesTheCaptionAsHtml(): void
	{
		$doc = [
			'type' => 'doc',
			'content' => [[
				'type' => 'paragraph',
				'content' => [
					['type' => 'text', 'text' => 'Sudhaus', 'marks' => [['type' => 'italic']]],
				],
			]],
		];

		$this->assertSame(
			['html' => '<em>Sudhaus</em>'],
			$this->image(['caption' => ['zxx' => $doc]])->json()['caption'],
		);
		$this->assertSame(
			['html' => 'Old &amp; plain'],
			$this->image([], ['caption' => ['en' => 'Old & plain']])->json()['caption'],
		);
		$this->assertNull($this->image([])->json()['caption']);
	}

	public function testAnImageWithoutALinkStaysAlone(): void
	{
		$image = $this->image([]);

		$this->assertSame('', $image->link());
		$this->assertSame((string) $image->img(), (string) $image->linked());
		$this->assertNull($image->json()['link']);
	}

	public function testAnAddressCarriesTheLinkPolicy(): void
	{
		$image = $this->image(['link' => ['href' => 'https://example.com/?a=1&b=2', 'target' => '_blank']]);

		$this->assertSame('https://example.com/?a=1&b=2', $image->link());
		$this->assertSame(
			'<a href="https://example.com/?a=1&amp;b=2" target="_blank" rel="noopener">'
				. $image->img()
				. '</a>',
			(string) $image->linked(),
		);
		$this->assertSame(
			[
				'href' => 'https://example.com/?a=1&b=2',
				'target' => '_blank',
				'rel' => 'noopener',
			],
			$image->json()['link'],
		);
	}

	public function testAnAddressCarriesTheRelTheEditorAdded(): void
	{
		$image = $this->image(['link' => ['href' => '/about', 'rel' => 'nofollow']]);

		$this->assertSame(['href' => '/about', 'rel' => 'noopener nofollow'], $image->json()['link']);
	}

	public function testALinkedFileUsesItsPath(): void
	{
		$image = $this->image(['link' => ['asset' => self::UID]]);

		$this->assertSame('/assets/im/' . self::UID . '/pic.jpg', $image->link());
		$this->assertSame(
			'<a href="/assets/im/' . self::UID . '/pic.jpg"><b>x</b></a>',
			(string) $image->linked(new \Cosray\Markup('<b>x</b>')),
		);
	}

	public function testALinkThatCannotBeFollowedLeavesTheImageAlone(): void
	{
		foreach ([
			['href' => 'javascript:alert(1)'],
			['asset' => 'gone000000001'],
			'/a-string-from-before',
		] as $link) {
			$image = $this->image(['link' => $link]);

			$this->assertSame('', $image->link());
			$this->assertSame((string) $image->img(), (string) $image->linked());
		}
	}

	public function testALinkOnlyPrintsItsOwnAttributes(): void
	{
		$image = $this->image(['link' => ['href' => '/about', 'class' => 'stored', 'onclick' => 'x']]);

		$this->assertStringStartsWith(
			'<a href="/about" rel="noopener">',
			(string) $image->linked(),
		);
	}

	public function testPrintingGivesTheImgWhateverTheFieldDeclares(): void
	{
		$image = $this->image(self::FILLED, configure: static function (ImageField $field): void {
			$field->caption()->attribution()->linkable();
		});

		$this->assertStringStartsWith('<img ', (string) $image);
		$this->assertSame((string) $image->img(), (string) $image);
	}

	public function testAnImgUsesRootRelativeUrlsAndTheRenditionsSize(): void
	{
		$this->assertSame(
			'<img src="/assets/im/'
				. self::UID
				. '/pic.jpg" width="1000" height="500" alt="" data-path-original="/assets/im/'
				. self::UID
				. '/pic.jpg">',
			(string) $this->image([])->img(),
		);
		$this->assertStringContainsString(
			'/pic-thumb.jpg" width="400" height="200" alt=""',
			self::withoutFingerprints((string) $this->image([])->size('thumb')->img()),
		);
		$this->assertStringContainsString(
			'width="300" height="300"',
			(string) $this->image([])->size('square')->img(),
		);
	}

	public function testAnImgFollowsItsCrop(): void
	{
		$this->assertStringContainsString(
			'width="500" height="250"',
			(string) $this->image(['crop' => self::CROP])->img(),
		);
		$this->assertStringContainsString(
			'width="400" height="200"',
			(string) $this->image(['crop' => self::CROP])->size('thumb')->img(),
		);
		$this->assertStringContainsString(
			'width="1000" height="500"',
			(string) $this->image(['crop' => self::CROP], secret: null)->img(),
		);
	}

	public function testAnImgTakesFurtherAttributes(): void
	{
		$img = (string) $this->image(['focal' => ['x' => 0.3, 'y' => 0.4]])->img(
			'a"b',
			...['data-zoom' => '<2>'],
			loading: 'lazy',
			width: null,
			height: 7,
			style: 'color: red',
			alt: 'Mine',
		);

		$this->assertStringStartsWith('<img class="a&quot;b" src="', $img);
		$this->assertStringContainsString(' loading="lazy"', $img);
		$this->assertStringContainsString(' data-zoom="&lt;2&gt;"', $img);
		$this->assertStringContainsString(' alt="Mine"', $img);
		$this->assertStringContainsString(' height="7"', $img);
		$this->assertStringNotContainsString(' width=', $img);
		$this->assertStringContainsString(' style="object-position: 30% 40%; color: red"', $img);
	}

	public function testAnImgEscapesItsValues(): void
	{
		$img = (string) $this->image(['alt' => ['zxx' => '"><script>']])->img();

		$this->assertStringContainsString(' alt="&quot;&gt;&lt;script&gt;"', $img);
	}

	public function testAnSvgKeepsItsOwnPath(): void
	{
		$img = (string) $this->image([], mime: 'image/svg+xml')->size('thumb')->img();

		$this->assertStringContainsString('src="/assets/im/' . self::UID . '/pic.jpg"', $img);
		$this->assertStringContainsString('width="1000" height="500"', $img);
	}

	public function testAGifLeavesItsSizeOut(): void
	{
		$img = (string) $this->image([], mime: 'image/gif')->size('thumb')->img();

		$this->assertStringNotContainsString(' width=', $img);
	}

	public function testAFigureHoldsTheLinkCaptionAndAttribution(): void
	{
		$image = $this->image(self::FILLED);

		$this->assertSame(
			'<figure><a href="/about" rel="noopener">'
				. $image->img()
				. '</a><figcaption>Words <small>© &lt;Jane&gt;</small></figcaption></figure>',
			(string) $image->figure(),
		);
	}

	public function testAFigureStaysWithoutText(): void
	{
		$image = $this->image([]);

		$this->assertSame('<figure>' . $image->img() . '</figure>', (string) $image->figure());
	}

	public function testAFigureUsesTheCatalogsTexts(): void
	{
		$image = $this->image([], ['attribution' => '© Catalog']);

		$this->assertStringContainsString(
			'<figcaption><small>© Catalog</small></figcaption>',
			(string) $image->figure(),
		);
	}

	public function testAFigureTakesAnImageAndAClass(): void
	{
		$image = $this->image([]);

		$this->assertSame(
			'<figure class="a&quot;b"><img src="/x.jpg" alt=""></figure>',
			(string) $image->figure(new Markup('<img src="/x.jpg" alt="">'), 'a"b'),
		);
	}

	public function testASizeKeepsItsFigure(): void
	{
		$figure = self::withoutFingerprints((string) $this->image(self::FILLED)->size('thumb')->figure());

		$this->assertStringContainsString('/cache/im/' . self::UID . '/pic-thumb.jpg', $figure);
		$this->assertStringStartsWith('<figure>', $figure);
	}

	public function testAGoneFilePrintsNothing(): void
	{
		$image = $this->image(uid: 'gone000000001', meta: self::FILLED);

		$this->assertSame('', (string) $image);
		$this->assertSame('', (string) $image->figure());
	}

	public function testAGalleryWrapsAFigurePerImage(): void
	{
		$gallery = $this->gallery([
			['uid' => self::UID, 'meta' => ['alt' => ['zxx' => 'A <kettle>'], 'caption' => ['zxx' => 'Words']]],
			['uid' => self::UID],
			['uid' => 'gone000000001'],
		]);

		$this->assertSame(
			'<div class="cms-image-gallery">'
				. '<figure class="cms-image-gallery-item"><img src="/cache/im/'
				. self::UID
				. '/pic-block-thumb.jpg" width="480" height="240" alt="A &lt;kettle&gt;" data-path-original="/assets/im/'
				. self::UID
				. '/pic.jpg"><figcaption>Words</figcaption></figure>'
				. '<figure class="cms-image-gallery-item"><img src="/cache/im/'
				. self::UID
				. '/pic-block-thumb.jpg" width="480" height="240" alt="" data-path-original="/assets/im/'
				. self::UID
				. '/pic.jpg"></figure></div>',
			self::withoutFingerprints((string) $gallery->gallery()),
		);
	}

	public function testAGalleryOffersALargerSizeAndAPrefix(): void
	{
		$html = self::withoutFingerprints((string) $this->gallery([['uid' => self::UID]])->gallery(
			'thumb',
			'square',
			'site',
		));

		$this->assertStringStartsWith(
			'<div class="site-image-gallery"><figure class="site-image-gallery-item">',
			$html,
		);
		$this->assertStringContainsString('/pic-thumb.jpg"', $html);
		$this->assertStringContainsString('data-url-large="/cache/im/' . self::UID . '/pic-square.jpg"', $html);
	}

	public function testAGalleryCarriesItsSettings(): void
	{
		$html = (string) $this->gallery([['uid' => self::UID]], [
			'ratio' => ['zxx' => '4/3'],
			'crop' => ['zxx' => true],
		])->gallery();

		$this->assertStringStartsWith(
			'<div class="cms-image-gallery" data-ratio="4/3" style="--ratio: 4/3" data-crop>',
			$html,
		);
	}

	public function testAGalleryWithoutImagesIsEmpty(): void
	{
		$this->assertSame('', (string) $this->gallery([['uid' => 'gone000000001']])->gallery());
	}

	public function testAGalleryRefusesAnUnknownSize(): void
	{
		$gallery = $this->gallery([]);

		$this->expectException(\Cosray\Exception\RuntimeException::class);
		$gallery->gallery('no-such-size');
	}

	public function testWithoutASecretTheImageStaysWhole(): void
	{
		$image = $this->image(['crop' => self::CROP, 'focal' => ['x' => 0.35, 'y' => 0.2]], secret: null);

		$this->assertSame('/assets/im/' . self::UID . '/pic.jpg', $image->publicPath());
		$this->assertSame(
			'/cache/im/' . self::UID . '/pic-square.jpg',
			self::withoutFingerprints($image->size('square')->publicPath()),
		);
		$this->assertSame(1000, $image->json()['width']);
		$this->assertSame(['x' => 0.35, 'y' => 0.2], $image->focal());
	}

	public function testAnSvgStaysItself(): void
	{
		$image = $this->image(['crop' => self::CROP, 'focal' => ['x' => 0.35, 'y' => 0.2]], mime: 'image/svg+xml');

		$this->assertSame(
			'/assets/im/' . self::UID . '/pic.jpg',
			self::withoutFingerprints($image->size('square')->publicPath()),
		);
		$this->assertSame(['x' => 0.35, 'y' => 0.2], $image->focal());
	}

	/**
	 * @param array<string, mixed> $meta
	 * @param array<string, mixed> $catalog
	 */
	/**
	 * @param list<array<string, mixed>> $items
	 * @param array<string, mixed> $meta
	 */
	private function gallery(array $items, array $meta = []): Images
	{
		$value = $this->image([], items: $items, fieldMeta: $meta);
		$this->assertInstanceOf(Images::class, $value);

		return $value;
	}

	private function image(
		array $meta,
		array $catalog = [],
		#[SensitiveParameter]
		?string $secret = 'test-secret',
		string $mime = 'image/jpeg',
		?Closure $configure = null,
		string $uid = self::UID,
		?array $items = null,
		array $fieldMeta = [],
	): Image|Images {
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
		$data = [
			'value' => [Field::NEUTRAL_LOCALE => $items ?? [['uid' => $uid, 'meta' => $meta]]],
			'meta' => $fieldMeta,
		];
		$field = new ImageField('image', new FieldOwner($context, 'test-node'), new ValueContext('image', $data));
		$field->init(Services::withDefaults());

		if ($items === null) {
			$field->limit(1);
		}

		if ($configure !== null) {
			$configure($field);
		}

		return $field->value();
	}
}
