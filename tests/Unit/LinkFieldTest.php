<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Celema\Sire\Issue;
use Cosray\Assets\Asset;
use Cosray\Context;
use Cosray\Field\Link;
use Cosray\Field\Services;
use Cosray\Markup;
use Cosray\Node\FieldOwner;
use Cosray\Tests\TestCase;
use Cosray\Value\Link as LinkValue;
use Cosray\Value\ValueContext;

/**
 * @internal
 *
 * @coversNothing
 */
final class LinkFieldTest extends TestCase
{
	private const string UID = 'linkdoc000001';

	public function testAnAddressCarriesTheLinkPolicy(): void
	{
		$link = $this->value(['zxx' => ['href' => 'https://example.com/?a=1&b=2', 'target' => '_blank']]);

		$this->assertSame('https://example.com/?a=1&b=2', $link->url());
		$this->assertSame('https://example.com/?a=1&amp;b=2', (string) $link);
		$this->assertSame(
			'<a href="https://example.com/?a=1&amp;b=2" target="_blank" rel="noopener">Go</a>',
			(string) $link->a('Go'),
		);
		$this->assertSame(
			['href' => 'https://example.com/?a=1&b=2', 'target' => '_blank', 'rel' => 'noopener'],
			$link->json(),
		);
	}

	public function testAFileLinkLeadsToItsPath(): void
	{
		$link = $this->value(['zxx' => ['asset' => self::UID]]);

		$this->assertSame('/assets/do/' . self::UID . '/plan.pdf', $link->url());
		$this->assertSame('plan.pdf', $link->asset()?->filename);
		$this->assertNull($link->node());
	}

	public function testAPageLinkNamesItsNode(): void
	{
		$link = $this->value(['zxx' => ['node' => 'some-page-uid']]);

		$this->assertSame('some-page-uid', $link->node());
		$this->assertNull($link->asset());
	}

	public function testContentIsEscapedUnlessItIsMarkup(): void
	{
		$link = $this->value(['zxx' => ['href' => '/about']]);

		$this->assertSame('<a href="/about" rel="noopener">&lt;b&gt;</a>', (string) $link->a('<b>'));
		$this->assertSame(
			'<a href="/about" rel="noopener" class="button"><b>x</b></a>',
			(string) $link->a(new Markup('<b>x</b>'), class: 'button'),
		);
	}

	public function testALinkThatCannotBeFollowedLeavesTheContentAlone(): void
	{
		foreach ([
			['zxx' => ['href' => 'javascript:alert(1)']],
			['zxx' => ['asset' => 'gone000000001']],
			['zxx' => null],
			['zxx' => 'a-string'],
			[],
		] as $stored) {
			$link = $this->value($stored);

			$this->assertSame('', $link->url());
			$this->assertSame('&lt;Go&gt;', (string) $link->a('<Go>'));
		}

		$this->assertNull($this->value(['zxx' => ['asset' => 'gone000000001']])->json());
	}

	public function testOnlyAStoredTargetIsSet(): void
	{
		$this->assertTrue($this->value(['zxx' => ['asset' => 'gone000000001']])->isset());
		$this->assertFalse($this->value(['zxx' => ['target' => '_blank']])->isset());
		$this->assertFalse($this->value(['zxx' => null])->isset());
		$this->assertSame(
			['href' => '/about'],
			$this->value(['zxx' => ['href' => '/about', 'class' => 'x', 'onclick' => 'y', 'rel' => '']])->raw(),
		);
	}

	public function testATranslatedLinkFallsBackToTheDefaultLanguage(): void
	{
		$stored = ['en' => ['href' => '/en.pdf'], 'de' => ['href' => '/de.pdf']];

		$this->assertSame('/de.pdf', $this->value($stored, locale: 'de', translate: true)->url());
		$this->assertSame('/en.pdf', $this->value(['en' => ['href' => '/en.pdf'], 'de' => null], 'de', true)->url());
	}

	public function testItsReferencesAreTheTargetsOfEveryLanguage(): void
	{
		$this->assertSame(
			['assets' => [self::UID], 'nodes' => ['page-uid']],
			Link::references([
				'type' => Link::class,
				'value' => [
					'en' => ['asset' => self::UID],
					'de' => ['node' => 'page-uid'],
					'fr' => null,
				],
			]),
		);
	}

	public function testAStoredLinkNeedsExactlyOneSafeTarget(): void
	{
		$field = $this->field();

		$this->assertSame([], $this->issues($field, ['zxx' => ['node' => 'page-uid', 'target' => '_blank']]));
		$this->assertSame([], $this->issues($field, ['zxx' => null]));
		$this->assertNotSame([], $this->issues($field, ['zxx' => ['href' => '/a', 'node' => 'page-uid']]));
		$this->assertNotSame([], $this->issues($field, ['zxx' => ['href' => 'javascript:alert(1)']]));
		$this->assertNotSame([], $this->issues($field, ['zxx' => ['href' => '/a', 'rel' => 'opener']]));
	}

	public function testARequiredLinkNeedsATarget(): void
	{
		$field = $this->field();
		$field->required();

		$this->assertNotSame([], $this->issues($field, ['zxx' => null]));
		$this->assertSame([], $this->issues($field, ['zxx' => ['href' => '/a']]));
	}

	public function testARequiredTranslatedLinkNeedsOnlyTheDefaultLanguage(): void
	{
		$field = $this->field(translate: true);
		$field->required();

		$this->assertSame([], $this->issues($field, ['en' => ['href' => '/a'], 'de' => null]));
		$this->assertNotSame([], $this->issues($field, ['en' => null, 'de' => ['href' => '/a']]));
	}

	public function testAnEmptyStructureValidates(): void
	{
		foreach ([false, true] as $translate) {
			$field = $this->field(translate: $translate);

			$this->assertTrue($field->shape()->validate($field->structure())->valid());
		}
	}

	public function testABareLinkIsTheStructureOfTheDefaultLanguage(): void
	{
		$this->assertSame(['zxx' => ['href' => '/a']], $this->field()->structure(['href' => '/a'])['value']);
		$this->assertSame(
			['en' => ['href' => '/a'], 'de' => null],
			$this->field(translate: true)->structure(['href' => '/a'])['value'],
		);
	}

	/**
	 * @param array<string, mixed> $value
	 * @return list<string>
	 */
	private function issues(Link $field, array $value): array
	{
		$result = $field->shape()->validate(['type' => Link::class, 'value' => $value]);

		return array_map(static fn(Issue $issue): string => $issue->message, $result->issues());
	}

	/** @param array<string, mixed> $stored */
	private function value(array $stored, string $locale = 'en', bool $translate = false): LinkValue
	{
		$field = $this->field(['type' => Link::class, 'value' => $stored], $locale, $translate);

		return $field->value();
	}

	/** @param array<string, mixed> $data */
	private function field(array $data = [], string $locale = 'en', bool $translate = false): Link
	{
		$field = new Link(
			'target',
			new FieldOwner($this->context($locale), 'test-node'),
			new ValueContext('target', $data),
		);
		$field->init(Services::withDefaults());

		if ($translate) {
			$field->translate();
		}

		return $field;
	}

	private function context(string $locale): Context
	{
		$locales = new \Cosray\Locales();
		$locales->add('en', title: 'English', domains: ['www.example.com']);
		$locales->add('de', title: 'Deutsch', fallback: 'en');
		$psrRequest = $this
			->psrRequest()
			->withAttribute('locales', $locales)
			->withAttribute('locale', $locales->get($locale))
			->withAttribute('defaultLocale', $locales->getDefault());
		$context = new Context(
			$this->db(),
			new \Celema\Core\Request($psrRequest),
			$this->config(),
			$this->container(),
			$this->factory(),
		);
		$context->assets()->add(new Asset(
			uid: self::UID,
			disk: 'local',
			key: 'do/' . self::UID . '/plan.pdf',
			filename: 'plan.pdf',
			mime: 'application/pdf',
		));

		return $context;
	}
}
