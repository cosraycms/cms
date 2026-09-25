<?php

declare(strict_types=1);

namespace Cosray\Tests\Integration;

use Celema\Core\Exception\HttpBadRequest;
use Cosray\Actor;
use Cosray\Block as Builtin;
use Cosray\Cms;
use Cosray\Context;
use Cosray\Field\Field;
use Cosray\Locales;
use Cosray\Node\PathManager;
use Cosray\Node\Store;
use Cosray\Node\Writer;
use Cosray\Tests\Fixtures\Block\TextBlock;
use Cosray\Tests\Fixtures\Node\TestMediaDocument;
use Cosray\Tests\IntegrationTestCase;
use Cosray\Value\Blocks;

/**
 * Blocks persist through the store in the typed-row shape and come back
 * through the finder as block values.
 *
 * @internal
 *
 * @coversNothing
 */
final class BlocksPersistenceTest extends IntegrationTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixtures('basic-types', 'sample-nodes');
	}

	private Context $context;
	private Cms $cms;

	private function writer(): Writer
	{
		$locales = new Locales();
		$locales->add('en', title: 'English', domains: ['www.example.com']);
		$locales->add('de', title: 'Deutsch', domains: ['www.example.de'], fallback: 'en');
		$this->context = Context::console(
			$this->db(),
			$this->config(),
			$this->container(),
			$this->factory(),
			$locales,
		);
		$services = self::blockServices();
		$this->cms = new Cms($this->context, $services);

		return new Writer($this->context, $this->cms, $services->types);
	}

	private function textRow(string $uid, string $text, array $layout): array
	{
		return [
			'uid' => $uid,
			'type' => TextBlock::class,
			'layout' => $layout,
			'fields' => [
				'text' => ['type' => \Cosray\Field\Textarea::class, 'value' => [Field::NEUTRAL_LOCALE => $text]],
			],
		];
	}

	private function headingRow(string $uid, string $text, string $level): array
	{
		return [
			'uid' => $uid,
			'type' => Builtin\Heading::class,
			'layout' => ['colspan' => 12, 'rowspan' => 1],
			'fields' => [
				'text' => ['type' => \Cosray\Field\Text::class, 'value' => [Field::NEUTRAL_LOCALE => $text]],
				'level' => ['type' => \Cosray\Field\Option::class, 'value' => [Field::NEUTRAL_LOCALE => $level]],
			],
			'meta' => ['class' => [Field::NEUTRAL_LOCALE => 'lead']],
		];
	}

	private function storedContent(string $uid): array
	{
		$row = $this->db()->execute('SELECT content FROM cms.nodes WHERE uid = :uid', ['uid' => $uid])->one();

		return json_decode((string) $row['content'], true);
	}

	public function testPerLocaleListsRoundTripThroughTheStore(): void
	{
		$writer = $this->writer();
		$prepared = $writer
			->prepare(TestMediaDocument::class, [
				'contentBlocks' => [
					'en' => [
						$this->headingRow('blockhead0001', 'Opening hours', '3'),
						$this->textRow('blocktext0001', "Mon-Fri\n9-17", ['colspan' => 6, 'rowspan' => 1]),
						$this->textRow('blocktext0002', 'Sat 9-12', ['colspan' => 4, 'rowspan' => 2]),
					],
					'de' => [
						$this->textRow('blocktext0003', 'Mo-Fr 9-17', ['colspan' => 12, 'rowspan' => 1]),
					],
				],
			])
			->uid('blocks-persist-node')
			->published();

		$writer->create($prepared);

		$stored = $this->storedContent('blocks-persist-node')['contentBlocks'];
		$this->assertSame(\Cosray\Field\Blocks::class, $stored['type']);
		// The grid the blocks were placed on is stored with them.
		$this->assertSame(12, $stored['columns']);
		$this->assertEqualsCanonicalizing(['en', 'de'], array_keys($stored['value']));
		$this->assertSame(
			['blockhead0001', 'blocktext0001', 'blocktext0002'],
			array_column($stored['value']['en'], 'uid'),
		);
		// Written without positions, the rows are stored placed in order.
		$this->assertEquals(
			['colspan' => 6, 'rowspan' => 1, 'col' => 1, 'row' => 2],
			$stored['value']['en'][1]['layout'],
		);
		$this->assertEquals(
			['colspan' => 4, 'rowspan' => 2, 'col' => 7, 'row' => 2],
			$stored['value']['en'][2]['layout'],
		);
		$this->assertSame(['class' => ['zxx' => 'lead']], $stored['value']['en'][0]['meta']);
		$this->assertSame(['zxx' => '3'], $stored['value']['en'][0]['fields']['level']['value']);
		$this->assertArrayNotHasKey('meta', $stored);

		$node = $this->createCms()->node->byUid('blocks-persist-node');
		$blocks = $node->contentBlocks;

		$this->assertInstanceOf(Blocks::class, $blocks);
		$this->assertSame(3, $blocks->count());
		$this->assertSame(12, $blocks->columns());
		$this->assertSame('heading', $blocks->first()?->handle());

		$html = $blocks->render();
		$this->assertStringContainsString('data-columns="12"', $html);
		$this->assertStringContainsString('<div class="cms-block lead" data-type="heading"', $html);
		$this->assertStringContainsString('<h3>Opening hours</h3>', $html);
		$this->assertStringContainsString('data-colspan="4" data-rowspan="2" data-col="7" data-row="2"', $html);
		$this->assertStringContainsString("Mon-Fri<br />\n9-17", $html);
		$this->assertStringNotContainsString('Mo-Fr', $html);
	}

	/**
	 * The writer's blueprint clamps layouts like every reader, so the
	 * rejection is exercised on the store with the raw data.
	 */
	public function testTheStoreRejectsRowsOutsideTheGrid(): void
	{
		$writer = $this->writer();
		$prepared = $writer
			->prepare(TestMediaDocument::class, [
				'contentBlocks' => [
					'en' => [$this->textRow('blocktext0009', 'Wide', [
						'colspan' => 6,
						'rowspan' => 1,
						'col' => 7,
						'row' => 1,
					])],
				],
			])
			->uid('blocks-invalid-node');
		$data = $prepared->data();
		$data['content']['contentBlocks']['value']['en'][0]['layout']['col'] = 8;
		$factory = $this->cms->nodeFactory();
		$store = new Store(
			$this->context->db,
			new PathManager(),
			$factory->hydrator()->services()->types,
			$factory->uid(),
			factory: $factory,
			cms: $this->cms,
			context: $this->context,
		);

		try {
			$store->create($prepared->node, $data, $this->context->locales(), Actor::system());
			$this->fail('A block past the grid\'s edge must be rejected');
		} catch (HttpBadRequest $e) {
			$paths = array_map(
				static fn(array $issue): string => implode('.', $issue['path']),
				json_decode(json_encode($e->payload()['errors']), true),
			);

			$this->assertContains('content.contentBlocks.value.en.0.layout.col', $paths);
		}

		$this->assertNull(
			$this->db()->execute('SELECT node FROM cms.nodes WHERE uid = :uid', [
				'uid' => 'blocks-invalid-node',
			])->first(),
		);
	}
}
