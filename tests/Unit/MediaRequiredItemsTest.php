<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Celema\Sire\Issue;
use Celema\Verba\Translator;
use Celema\Verba\Verba;
use Cosray\Assets\Asset;
use Cosray\Context;
use Cosray\Exception\RuntimeException;
use Cosray\Field\Field;
use Cosray\Field\File;
use Cosray\Field\Image as ImageField;
use Cosray\Field\Schema\AltHandler;
use Cosray\Field\Schema\AttributionHandler;
use Cosray\Field\Schema\CaptionHandler;
use Cosray\Field\Schema\LinkHandler;
use Cosray\Field\Services;
use Cosray\Field\Video;
use Cosray\Node\FieldOwner;
use Cosray\Schema\Alt;
use Cosray\Schema\Attribution;
use Cosray\Schema\Caption;
use Cosray\Schema\Link;
use Cosray\Schema\TranslateMode;
use Cosray\Tests\TestCase;
use Cosray\Value\ValueContext;

/**
 * @internal
 *
 * @coversNothing
 */
final class MediaRequiredItemsTest extends TestCase
{
	private const string UID = 'img0000000001';
	private const string BARE = 'img0000000002';

	public function testRequiredPartsComeFromTheAttributes(): void
	{
		$field = $this->field();
		new AltHandler()->apply(new Alt(required: true), $field);
		new CaptionHandler()->apply(new Caption(required: true), $field);
		new AttributionHandler()->apply(new Attribution(), $field);
		new LinkHandler()->apply(new Link(required: true), $field);

		$this->assertSame(['alt', 'caption', 'link'], $field->requiredItems());
		$this->assertTrue($field->hasAttribution());
		$this->assertSame(['alt', 'caption', 'link'], $field->properties()['requiredItems']);
	}

	public function testAFieldWithoutRequiredPartsStaysAsItWas(): void
	{
		$field = $this->field();
		new AltHandler()->apply(new Alt(), $field);

		$this->assertSame([], $field->requiredItems());
		$this->assertArrayNotHasKey('requiredItems', $field->properties());
		$this->assertSame([], $this->issues($field, $this->single(self::BARE)));
	}

	public function testAltOnlyAppliesToImages(): void
	{
		$field = new Video('clip', new FieldOwner($this->context(), 'test-node'), new ValueContext('clip', []));
		$field->init(Services::withDefaults());

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('cannot be used with the capability');

		new AltHandler()->apply(new Alt(required: true), $field);
	}

	public function testAMissingPartNamesItsPlace(): void
	{
		$field = $this->field()->requireItem('alt');
		$issues = $this->issues($field, $this->single(self::BARE));

		$this->assertCount(1, $issues);
		$this->assertSame(['value', 'zxx', 0, 'meta', 'alt'], $issues[0]['path']);
		$this->assertSame('media:item-missing', $issues[0]['message']);
	}

	public function testAGalleryNamesTheImage(): void
	{
		$field = $this->field(limit: false)->requireItem('alt');
		$issues = $this->issues($field, [
			Field::NEUTRAL_LOCALE => [
				['uid' => self::BARE, 'meta' => ['alt' => ['zxx' => 'Fine']]],
				['uid' => self::BARE],
			],
		]);

		$this->assertCount(1, $issues);
		$this->assertSame(['value', 'zxx', 1, 'meta', 'alt'], $issues[0]['path']);
		$this->assertSame('media:item-missing-at', $issues[0]['message']);
	}

	public function testTheMessagesNameTheField(): void
	{
		$single = $this->field()->requireItem('caption');
		$single->label('Cover');
		$gallery = $this->field(limit: false)->requireItem('alt');
		$gallery->label('Gallery');

		Verba::activate(new Translator('de', ['cosray' => self::root() . '/lang']));

		try {
			$this->assertSame(
				'Cover: Bildunterschrift fehlt.',
				$this->issues($single, $this->single(self::BARE))[0]['message'],
			);
			$this->assertSame(
				'Gallery, Datei 1: Alternativtext fehlt.',
				$this->issues($gallery, $this->single(self::BARE))[0]['message'],
			);
		} finally {
			Verba::deactivate();
		}
	}

	public function testAnEmptyFieldNeedsNothing(): void
	{
		$field = $this->field()->requireItem('alt')->requireItem('link');

		$this->assertSame([], $this->issues($field, [Field::NEUTRAL_LOCALE => []]));
	}

	public function testAUsesOwnTextAndTheCatalogsBothCount(): void
	{
		$field = $this->field()->requireItem('alt')->requireItem('caption')->requireItem('attribution');

		$this->assertSame([], $this->issues($field, $this->single(self::UID)));
		$this->assertSame(
			[],
			$this->issues($field, $this->single(self::BARE, [
				'alt' => ['zxx' => 'Own'],
				'caption' => [
					'zxx' => [
						'type' => 'doc',
						'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Own']]]],
					],
				],
				'attribution' => '© Own',
			])),
		);
	}

	public function testBlankTextDoesNotCount(): void
	{
		$field = $this->field()->requireItem('alt')->requireItem('attribution');

		$this->assertCount(2, $this->issues($field, $this->single(self::BARE, [
			'alt' => ['zxx' => '   '],
			'attribution' => ' ',
		])));
	}

	public function testALinkMustBeTheUsesOwn(): void
	{
		$field = $this->field()->requireItem('link');

		$this->assertCount(1, $this->issues($field, $this->single(self::UID)));
		$this->assertSame([], $this->issues($field, $this->single(self::UID, ['link' => ['href' => '/about']])));
	}

	public function testATranslatedTextIsOnlyRequiredInTheDefaultLanguage(): void
	{
		$field = $this->field(locales: true)->requireItem('alt');
		$field->translate(TranslateMode::Symmetric);

		$this->assertSame([], $this->issues($field, $this->single(self::BARE, ['alt' => ['en' => 'Own']])));
		$this->assertCount(1, $this->issues($field, $this->single(self::BARE, ['alt' => ['de' => 'Eigen']])));
	}

	public function testAnAsymmetricFieldOnlyChecksItsDefaultList(): void
	{
		$field = $this->field(locales: true)->requireItem('alt');
		$field->translate(TranslateMode::Asymmetric);

		$issues = $this->issues($field, ['en' => [['uid' => self::UID]], 'de' => [['uid' => self::BARE]]]);

		$this->assertSame([], $issues);
		$this->assertCount(1, $this->issues($field, ['en' => [['uid' => self::BARE]], 'de' => [['uid' => self::UID]]]));
	}

	public function testAVideoCanRequireItsCaption(): void
	{
		$field = new Video('clip', new FieldOwner($this->context(), 'test-node'), new ValueContext('clip', []));
		$field->init(Services::withDefaults());
		$field->limit(1);
		new CaptionHandler()->apply(new Caption(required: true), $field);

		$this->assertCount(1, $this->issues($field, $this->single(self::BARE)));
	}

	/**
	 * @param array<string, mixed> $meta
	 * @return array<string, list<array<string, mixed>>>
	 */
	private function single(string $uid, array $meta = []): array
	{
		return [Field::NEUTRAL_LOCALE => [['uid' => $uid, ...($meta === [] ? [] : ['meta' => $meta])]]];
	}

	/**
	 * @param array<string, mixed> $value
	 * @return list<array<string, mixed>>
	 */
	private function issues(File $field, array $value): array
	{
		$result = $field->shape()->validate(['type' => $field::class, 'value' => $value]);

		return array_map(
			static fn(Issue $issue): array => ['path' => $issue->path, 'message' => $issue->message],
			$result->issues(),
		);
	}

	private function field(bool $limit = true, bool $locales = false): ImageField
	{
		$field = new ImageField(
			'image',
			new FieldOwner($this->context($locales), 'test-node'),
			new ValueContext('image', []),
		);
		$field->init(Services::withDefaults());

		if ($limit) {
			$field->limit(1);
		}

		return $field;
	}

	private function context(bool $translated = false): Context
	{
		$psrRequest = $this->psrRequest();
		$locales = new \Cosray\Locales();
		$locales->add('en', title: 'English', domains: ['www.example.com']);

		if ($translated) {
			$locales->add('de', title: 'Deutsch', fallback: 'en');
		}

		$psrRequest = $psrRequest
			->withAttribute('locales', $locales)
			->withAttribute('locale', $locales->get('en'))
			->withAttribute('defaultLocale', $locales->getDefault());
		$context = new Context(
			$this->db(),
			new \Celema\Core\Request($psrRequest),
			$this->config(),
			$this->container(),
			$this->factory(),
		);
		$context->assets()->add($this->asset(self::UID, [
			'alt' => ['en' => 'From the catalog'],
			'caption' => ['en' => 'From the catalog'],
			'attribution' => '© Catalog',
		]));
		$context->assets()->add($this->asset(self::BARE, []));

		return $context;
	}

	/** @param array<string, mixed> $meta */
	private function asset(string $uid, array $meta): Asset
	{
		return new Asset(
			uid: $uid,
			disk: 'local',
			key: "im/{$uid}/pic.jpg",
			filename: 'pic.jpg',
			mime: 'image/jpeg',
			width: 1000,
			height: 500,
			meta: $meta,
		);
	}
}
