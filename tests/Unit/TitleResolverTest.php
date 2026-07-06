<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Locale;
use Cosray\Locales;
use Cosray\Node\Types;
use Cosray\Tests\Fixtures\Node\NodeWithClassTitleAttribute;
use Cosray\Tests\Fixtures\Node\NodeWithNumericTitleField;
use Cosray\Tests\Fixtures\Node\NodeWithPropertyTitleAttribute;
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

	private function locales(): Locales
	{
		$locales = new Locales();
		$locales->add('en', title: 'English');
		$locales->add('de', title: 'Deutsch', fallback: 'en');

		return $locales;
	}
}
