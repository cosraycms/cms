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
