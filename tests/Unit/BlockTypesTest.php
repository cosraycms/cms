<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Celema\Core\Request;
use Cosray\Assets\Repository;
use Cosray\Block\Registry;
use Cosray\Block\RenderContext;
use Cosray\Block\Types\Heading;
use Cosray\Config;
use Cosray\Exception\RuntimeException;
use Cosray\Field\Blocks;
use Cosray\Field\Owner;
use Cosray\Field\Services;
use Cosray\Locale;
use Cosray\Locales;
use Cosray\Node\UrlPaths;
use Cosray\Tests\TestCase;
use Cosray\Value\Block;
use Cosray\Value\ValueContext;

/**
 * @internal
 *
 * @coversNothing
 */
final class BlockTypesTest extends TestCase
{
	public function testDefaultRegistryContents(): void
	{
		$registry = Registry::withDefaults();
		$ids = array_map(static fn($type) => $type->id(), $registry->all());

		foreach (['richtext', 'text', 'image', 'images', 'youtube', 'video', 'iframe'] as $id) {
			$this->assertContains($id, $ids);
		}

		foreach (range(1, 6) as $level) {
			$this->assertContains("h{$level}", $ids);
			$this->assertTrue($registry->get("h{$level}")->hidden());
		}
	}

	public function testUnknownBlockTypeThrowsWithHint(): void
	{
		$this->throws(RuntimeException::class, 'Registrar::blockType()');

		Registry::withDefaults()->get('nope');
	}

	public function testTextAndHeadingRender(): void
	{
		$registry = Registry::withDefaults();
		$ctx = $this->context();
		$block = new Block('text', ['value' => ['zxx' => 'hello']]);

		$this->assertSame('hello', $registry->get('text')->render($block, $ctx));
		$this->assertSame(
			'<h2>hello</h2>',
			$registry->get('h2')->render(new Block('h2', ['value' => ['zxx' => 'hello']]), $ctx),
		);
	}

	public function testRenderResolvesLocaleFallback(): void
	{
		$ctx = $this->context();
		$block = new Block('text', ['value' => ['de' => '', 'en' => 'fallback']]);

		$this->assertSame('fallback', Registry::withDefaults()->get('text')->render($block, $ctx));
	}

	public function testRichtextBlockRendersStructuredDocuments(): void
	{
		$ctx = $this->context();
		$block = new Block('richtext', [
			'format' => 'cosray-richtext',
			'version' => 1,
			'value' => [
				'de' => [
					'type' => 'doc',
					'content' => [
						[
							'type' => 'paragraph',
							'content' => [
								['type' => 'text', 'text' => 'fett', 'marks' => [['type' => 'bold']]],
							],
						],
					],
				],
			],
		]);

		$this->assertSame(
			'<p><strong>fett</strong></p>',
			Registry::withDefaults()->get('richtext')->render($block, $ctx),
		);

		// Unmigrated legacy HTML no longer renders — migration 020 ships
		// with this code.
		$legacy = new Block('richtext', ['value' => ['zxx' => '<p>alt</p>']]);

		$this->assertSame('', Registry::withDefaults()->get('richtext')->render($legacy, $ctx));
	}

	public function testBlocksFieldPropertiesExposeBlockTypes(): void
	{
		$field = $this->blocksField();
		$types = array_column($field->properties()['blockTypes'], null, 'id');

		$this->assertSame('Formatierter Text', $types['richtext']['label']);
		$this->assertSame('block-richtext', $types['richtext']['control']['name']);
		$this->assertSame(['zxx' => null], $types['richtext']['init']['value']);
		$this->assertSame('cosray-richtext', $types['richtext']['init']['format']);
		$this->assertSame(1, $types['richtext']['init']['version']);
		$this->assertTrue($types['h1']['hidden']);
		$this->assertSame(['zxx' => 16], $types['youtube']['init']['meta']['aspectRatioX']);
		$this->assertSame([], $types['image']['init']['value']);
	}

	public function testAllowRestrictsBlockTypes(): void
	{
		$field = $this->blocksField()->allow('richtext', 'text');
		$ids = array_column($field->properties()['blockTypes'], 'id');

		$this->assertSame(['richtext', 'text'], $ids);
	}

	private function blocksField(): Blocks
	{
		$field = new Blocks('content', $this->owner(), new ValueContext('content', []));
		$field->init(Services::withDefaults());

		return $field;
	}

	private function context(): RenderContext
	{
		return new RenderContext($this->owner(), 'content', 12, []);
	}

	private function owner(): Owner
	{
		$locales = new Locales();
		$locales->add('en', title: 'English');
		$locales->add('de', title: 'Deutsch', fallback: 'en');

		return new class($this->config(), $locales) implements Owner {
			public function __construct(
				private readonly Config $config,
				private readonly Locales $locales,
			) {}

			public function uid(): string
			{
				return 'test-node';
			}

			public function locale(): Locale
			{
				return $this->locales->get('de');
			}

			public function defaultLocale(): Locale
			{
				return $this->locales->getDefault();
			}

			public function locales(): Locales
			{
				return $this->locales;
			}

			public function request(): Request
			{
				throw new RuntimeException('Not available in this test');
			}

			public function config(): Config
			{
				return $this->config;
			}

			public function assets(): Repository
			{
				throw new RuntimeException('Not available in this test');
			}

			public function paths(): UrlPaths
			{
				throw new RuntimeException('Not available in this test');
			}
		};
	}
}
