<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Exception\RoutePathError;
use Cosray\Field\Text;
use Cosray\Locales;
use Cosray\Node\RoutePathGenerator;
use Cosray\Node\Types;
use Cosray\Tests\TestCase;

/**
 * @internal
 *
 * @covers \Cosray\Node\RoutePathGenerator
 */
final class RoutePathGeneratorTest extends TestCase
{
	public function testRoutePlaceholdersSupportTransformers(): void
	{
		$paths = $this->generator()->generateFromRoute(
			[
				'en' => '/stations/{title}/{title|uppercase}/{title|titlecase}/{title|underscore}',
			],
			$this->nodeData('central station'),
			$this->locales(),
		);

		$this->assertSame(
			'/stations/central-station/CENTRAL-STATION/Central-Station/central_station',
			$paths['en'],
		);
	}

	public function testRoutePlaceholderTransformersUseLastCaseAndSeparator(): void
	{
		$paths = $this->generator()->generateFromRoute(
			'/stations/{title|uppercase|lowercase|underscore|dashes}',
			$this->nodeData('Central Station'),
			$this->locales(),
		);

		$this->assertSame('/stations/central-station', $paths['en']);
	}

	public function testRoutePlaceholderKeepcaseTransformerPreservesCase(): void
	{
		$paths = $this->generator()->generateFromRoute(
			'/stations/{title|keepcase}/{title|keepcase|underscore}/{title|uppercase|keepcase}/{title|keepcase|lowercase}',
			$this->nodeData('ICE 728 Aa'),
			$this->locales(),
		);

		$this->assertSame('/stations/ICE-728-Aa/ICE_728_Aa/ICE-728-Aa/ice-728-aa', $paths['en']);
	}

	public function testUnknownRoutePlaceholderTransformerFailsInStrictMode(): void
	{
		$this->throws(RoutePathError::class, 'Unknown route path transformer: unknown');

		$this->generator()->generateFromRoute(
			'/stations/{title|unknown}',
			$this->nodeData('Central Station'),
			$this->locales(),
		);
	}

	public function testParentDepthIsLimitedInStrictMode(): void
	{
		$this->throws(RoutePathError::class, 'Route path parent depth cannot exceed 5');

		$this->generator()->generateFromRoute(
			'/{parent(6).title}',
			$this->nodeData('Central Station'),
			$this->locales(),
		);
	}

	public function testParentPathTransformersFailInStrictMode(): void
	{
		$this->throws(
			RoutePathError::class,
			'Route path transformers are not supported for parent path placeholders',
		);

		$this->generator()->generateFromRoute(
			'/{parent(2)|lowercase}',
			$this->nodeData('Central Station'),
			$this->locales(),
		);
	}

	public function testOptionalParentPathIsOmittedWhenMissing(): void
	{
		$paths = $this->generator()->generateFromRoute(
			'/pages/{parent?}/{title}',
			$this->nodeData('Central Station'),
			$this->locales(),
		);

		$this->assertSame('/pages/central-station', $paths['en']);
	}

	public function testOnlyDirectParentPathCanBeOptional(): void
	{
		$this->throws(RoutePathError::class, 'Invalid route path parent syntax');

		$this->generator()->generateFromRoute(
			'/{parent(2)?}/{title}',
			$this->nodeData('Central Station'),
			$this->locales(),
		);
	}

	public function testOptionalParentPathTransformersFailInStrictMode(): void
	{
		$this->throws(
			RoutePathError::class,
			'Route path transformers are not supported for parent path placeholders',
		);

		$this->generator()->generateFromRoute(
			'/{parent?|lowercase}/{title}',
			$this->nodeData('Central Station'),
			$this->locales(),
		);
	}

	public function testPreviewModeUsesFriendlyMissingAncestorPlaceholders(): void
	{
		$paths = $this->generator()->generateFromRoute(
			'/{parent(1)}/{parent(2)}/{parent(1).countryCode|lowercase}/{parent(2).countryCode|lowercase}',
			$this->nodeData('Central Station'),
			$this->locales(),
			strict: false,
		);

		$this->assertSame(
			'/[parent path]/[ancestor path]/[parent country code]/[ancestor country code]',
			$paths['en'],
		);
	}

	public function testPreviewModeUsesFriendlyMissingPlaceholders(): void
	{
		$paths = $this->generator()->generateFromRoute(
			'/stations/{countryCode|lowercase}-{stationId}-{title|underscore}/',
			[
				'content' => [],
			],
			$this->locales(),
			strict: false,
		);

		$this->assertSame('/stations/[country code]-[station id]-[title]/', $paths['en']);
	}

	public function testUnknownRoutePlaceholderTransformerUsesFriendlyPlaceholderInPreviewMode(): void
	{
		$paths = $this->generator()->generateFromRoute(
			'/stations/{title|unknown}',
			$this->nodeData('Central Station'),
			$this->locales(),
			strict: false,
		);

		$this->assertSame('/stations/[title]', $paths['en']);
	}

	public function testReferencedFieldsListsContentFieldsOnly(): void
	{
		$generator = $this->generator();

		// Bare names are this node's content fields; uid/handle/parent are not.
		$this->assertSame(['title'], $generator->referencedFields('/pages/{title}'));
		$this->assertSame([], $generator->referencedFields('/test/{uid}'));
		$this->assertSame([], $generator->referencedFields('/x/{handle}'));
		$this->assertSame(['title'], $generator->referencedFields('/{parent.title}/{title}'));
		$this->assertSame([], $generator->referencedFields('/{parent(2).countryCode}/{parent}'));
		$this->assertSame([], $generator->referencedFields('/{parent?}/{parent(1).title}'));

		// Transformers are stripped from the selector.
		$this->assertSame(['title'], $generator->referencedFields('/stations/{title|lowercase}'));

		// Distinct fields keep first-seen order.
		$this->assertSame(
			['countryCode', 'stationId', 'title'],
			$generator->referencedFields('/{countryCode|lowercase}-{stationId}-{title|underscore}'),
		);

		// A per-locale map is the union of all templates, de-duplicated.
		$this->assertSame(
			['title', 'subtitle'],
			$generator->referencedFields(['de' => '/de/{title}', 'en' => '/en/{title}/{subtitle}']),
		);

		// No route / non-string route yields nothing.
		$this->assertSame([], $generator->referencedFields(null));
		$this->assertSame([], $generator->referencedFields('/static/path'));
	}

	private function generator(): RoutePathGenerator
	{
		return new RoutePathGenerator($this->db(), new Types());
	}

	private function locales(): Locales
	{
		$locales = new Locales();
		$locales->add('en', title: 'English');

		return $locales;
	}

	/** @return array<string, mixed> */
	private function nodeData(string $title): array
	{
		return [
			'content' => [
				'title' => [
					'type' => Text::class,
					'value' => ['en' => $title],
				],
			],
		];
	}
}
