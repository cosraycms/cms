<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Exception\RuntimeException;
use Cosray\Locale;
use Cosray\Locales;
use Cosray\Node\Types;
use Cosray\Tests\Fixtures\Node\NodeWithAmbiguousEmbeddedTitles;
use Cosray\Tests\Fixtures\Node\NodeWithClassTitleAttribute;
use Cosray\Tests\Fixtures\Node\NodeWithEmbeddedTitleField;
use Cosray\Tests\Fixtures\Node\NodeWithExplicitEmbeddedTitle;
use Cosray\Tests\Fixtures\Node\NodeWithNumericTitleField;
use Cosray\Tests\Fixtures\Node\NodeWithPropertyTitleAttribute;
use Cosray\Tests\Fixtures\Node\TestEmbeddedDocument;
use Cosray\Tests\Fixtures\Node\TestPage;
use Cosray\Tests\TestCase;
use Cosray\Title\Resolver;

/**
 * @internal
 *
 * @coversNothing
 */
final class TitleResolverTest extends TestCase
{
	public function testDescriptorClassifiesTitleSources(): void
	{
		$resolver = new Resolver(new Types());

		// Contract\Title wins over any field.
		$this->assertSame(Resolver::KIND_DYNAMIC, $resolver->descriptor(TestPage::class)['kind']);

		// #[Title('heading')] on the class.
		$this->assertSame(
			['kind' => Resolver::KIND_FIELD, 'field' => 'heading'],
			$resolver->descriptor(NodeWithClassTitleAttribute::class),
		);

		// #[Title] on a text property.
		$this->assertSame(
			['kind' => Resolver::KIND_FIELD, 'field' => 'heading'],
			$resolver->descriptor(NodeWithPropertyTitleAttribute::class),
		);

		// A non-text title field is not a usable title source.
		$this->assertSame(
			Resolver::KIND_NONE,
			$resolver->descriptor(NodeWithNumericTitleField::class)['kind'],
		);

		$this->assertSame(
			['kind' => Resolver::KIND_DYNAMIC, 'embedded' => 'baseFields'],
			$resolver->descriptor(TestEmbeddedDocument::class),
		);
		$this->assertSame(
			['kind' => Resolver::KIND_DYNAMIC, 'embedded' => 'baseFields'],
			$resolver->descriptor(NodeWithExplicitEmbeddedTitle::class),
		);
		$this->assertSame(
			['kind' => Resolver::KIND_FIELD, 'field' => 'heading'],
			$resolver->descriptor(NodeWithEmbeddedTitleField::class),
		);
	}

	public function testDescriptorRejectsAmbiguousEmbeddedProviders(): void
	{
		$this->throws(RuntimeException::class, 'multiple embedded title providers');

		new Resolver(new Types())->descriptor(NodeWithAmbiguousEmbeddedTitles::class);
	}

	public function testFieldMapKeepsLocalesAndDropsBlanks(): void
	{
		$resolver = new Resolver(new Types());

		$content = [
			'heading' => ['value' => ['en' => ' Hello ', 'de' => '', 'fr' => 'Bonjour']],
		];

		$this->assertSame(
			['en' => 'Hello', 'fr' => 'Bonjour'],
			$resolver->fieldMap($content, 'heading'),
		);

		$this->assertSame([], $resolver->fieldMap([], 'heading'));
	}

	public function testDynamicMapCollapsesIdenticalLocalesToNeutral(): void
	{
		$resolver = new Resolver(new Types());

		$map = $resolver->dynamicMap(
			static fn(Locale $locale): string => 'Submission 42',
			$this->locales(),
		);

		$this->assertSame(['zxx' => 'Submission 42'], $map);
	}

	public function testDynamicMapKeepsDistinctLocales(): void
	{
		$resolver = new Resolver(new Types());

		$map = $resolver->dynamicMap(
			static fn(Locale $locale): string => $locale->id === 'de' ? 'Hallo' : 'Hello',
			$this->locales(),
		);

		$this->assertSame(['en' => 'Hello', 'de' => 'Hallo'], $map);
	}

	public function testDynamicMapDoesNotCollapseWhenALocaleIsBlank(): void
	{
		$resolver = new Resolver(new Types());

		$map = $resolver->dynamicMap(
			static fn(Locale $locale): string => $locale->id === 'en' ? 'Only English' : '',
			$this->locales(),
		);

		$this->assertSame(['en' => 'Only English'], $map);
	}

	public function testStoredPrefersTheActiveLocale(): void
	{
		$resolver = new Resolver(new Types());
		$locales = $this->locales();
		$map = ['en' => 'Hello', 'de' => 'Hallo'];

		$this->assertSame('Hallo', $resolver->stored($map, $locales->get('de')));
		$this->assertSame('Hello', $resolver->stored($map, $locales->get('en')));
	}

	public function testStoredWalksTheLocaleFallbackChain(): void
	{
		$resolver = new Resolver(new Types());

		$this->assertSame('Hello', $resolver->stored(['en' => 'Hello'], $this->locales()->get('de')));
	}

	public function testStoredFallsBackToTheNeutralKey(): void
	{
		$resolver = new Resolver(new Types());

		// A collapsed map (see dynamicMap) carries only the neutral key.
		$this->assertSame(
			'Submission 42',
			$resolver->stored(['zxx' => 'Submission 42'], $this->locales()->get('de')),
		);
		$this->assertSame('Submission 42', $resolver->stored(['zxx' => 'Submission 42'], null));
	}

	public function testStoredReportsNothingUsable(): void
	{
		$resolver = new Resolver(new Types());
		$locale = $this->locales()->get('de');

		// Null is the caller's signal to resolve the title live instead.
		$this->assertNull($resolver->stored([], $locale));
		$this->assertNull($resolver->stored(['it' => 'Ciao'], $locale));
		$this->assertNull($resolver->stored(['en' => 'Hello'], null));
	}

	public function testStoredSkipsBlankAndNonStringEntries(): void
	{
		$resolver = new Resolver(new Types());
		$locale = $this->locales()->get('de');

		$this->assertSame('Hello', $resolver->stored(['de' => '   ', 'en' => 'Hello'], $locale));
		$this->assertSame('Hello', $resolver->stored(['de' => 42, 'en' => 'Hello'], $locale));
		$this->assertNull($resolver->stored(['de' => '', 'zxx' => ''], $locale));
	}

	private function locales(): Locales
	{
		$locales = new Locales();
		$locales->add('en', title: 'English');
		$locales->add('de', title: 'Deutsch', fallback: 'en');

		return $locales;
	}
}
