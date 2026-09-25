<?php

declare(strict_types=1);

namespace Cosray\Tests\Integration;

use Cosray\Actor;
use Cosray\Cms;
use Cosray\Context;
use Cosray\Exception\RuntimeException;
use Cosray\Field\Services;
use Cosray\Locales;
use Cosray\Node\PathManager;
use Cosray\Node\Store;
use Cosray\Node\Writer;
use Cosray\Tests\Fixtures\Node\PlainBlock;
use Cosray\Tests\Fixtures\Node\PlainPage;
use Cosray\Tests\IntegrationTestCase;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use ValueError;

/**
 * @internal
 *
 * @coversNothing
 */
final class NodeWriterTest extends IntegrationTestCase
{
	private Context $context;
	private Cms $cms;

	private function writer(): Writer
	{
		$locales = new Locales();
		$locales->add('en', title: 'English');
		$context = Context::console(
			$this->db(),
			$this->config(),
			$this->container(),
			$this->factory(),
			$locales,
		);
		$services = Services::withDefaults();

		$this->context = $context;
		$this->cms = new Cms($context, $services);

		return new Writer($context, $this->cms, $services->types);
	}

	private function storedNode(string $uid): array
	{
		return $this->db()->execute(
			'SELECT *, created = now() AS created_now, changed = now() AS changed_now
			FROM cms.nodes WHERE uid = :uid',
			['uid' => $uid],
		)->one();
	}

	private function activePath(string $uid): ?string
	{
		$row = $this->db()->execute(
			'SELECT path FROM cms.url_paths
			WHERE inactive IS NULL
				AND node = (SELECT node FROM cms.nodes WHERE uid = :uid)',
			['uid' => $uid],
		)->first();

		return $row['path'] ?? null;
	}

	public function testCreatesNodeWithoutHttpRequest(): void
	{
		$locales = new Locales();
		$locales->add('en', title: 'English');
		$context = Context::console(
			$this->db(),
			$this->config(),
			$this->container(),
			$this->factory(),
			$locales,
		);
		$services = Services::withDefaults();
		$writer = new Writer($context, new Cms($context, $services), $services->types);
		$prepared = $writer
			->prepare(PlainBlock::class, ['content' => 'Console content'])
			->uid('writer-console-node')
			->published()
			->fieldMeta('content', 'source', ['zxx' => 'console']);

		$result = $writer->create($prepared);
		$stored = $this->db()->execute(
			'SELECT uid, published, content FROM cms.nodes WHERE uid = :uid',
			['uid' => 'writer-console-node'],
		)->one();
		$content = json_decode((string) $stored['content'], true);

		$this->assertNull($context->request);
		$this->assertSame('writer-console-node', $result['uid']);
		$this->assertTrue($stored['published']);
		$this->assertSame('Console content', $content['content']['value']['zxx']);
		$this->assertSame('console', $content['content']['meta']['source']['zxx']);
	}

	#[DataProvider('actors')]
	public function testCreationDefaultsToTheActorAndCurrentTime(bool $explicitActor): void
	{
		$actor = $explicitActor ? new Actor($this->createTestUser(['uid' => 'writer-actor'])) : null;
		$writer = $this->writer();
		$writer->create($writer->prepare(PlainBlock::class)->uid('writer-defaults'), $actor);
		$row = $this->storedNode('writer-defaults');

		$this->assertSame($actor?->id ?? Actor::system()->id, $row['creator']);
		$this->assertSame($row['creator'], $row['editor']);
		$this->assertTrue($row['created_now']);
		$this->assertTrue($row['changed_now']);
	}

	public static function actors(): array
	{
		return ['system default' => [false], 'explicit actor' => [true]];
	}

	public function testHistoricalMetadataSurvivesCreationAndEntersHistoryOnEdit(): void
	{
		$creator = new Actor($this->createTestUser(['uid' => 'writer-creator']));
		$editor = new Actor($this->createTestUser(['uid' => 'writer-editor']));
		$created = new DateTimeImmutable('2016-01-02T03:04:05.123456+05:30');
		$changed = new DateTimeImmutable('2020-07-08T09:10:11.654321-04:00');
		$writer = $this->writer();
		$prepared = $writer->prepare(PlainBlock::class, ['content' => 'Imported'])->uid('writer-history');
		$writer->create($prepared, actor: $editor, creator: $creator, created: $created, changed: $changed);
		$row = $this->storedNode('writer-history');

		$this->assertSame($creator->id, $row['creator']);
		$this->assertSame($editor->id, $row['editor']);
		$this->assertEquals($created, new DateTimeImmutable($row['created']));
		$this->assertEquals($changed, new DateTimeImmutable($row['changed']));
		$this->assertSame(
			[],
			$this->db()->execute(
				'SELECT * FROM cms.nodes_history WHERE node = :node',
				['node' => $row['node']],
			)->all(),
		);

		$factory = $this->cms->nodeFactory();
		$node = $factory->create(PlainBlock::class, $this->context, $this->cms, [
			...$row,
			'content' => json_decode($row['content'], true),
		]);
		$store = new Store(
			$this->db(),
			new PathManager(),
			$factory->hydrator()->services()->types,
			$factory->uid(),
			factory: $factory,
			cms: $this->cms,
			context: $this->context,
		);
		$data = $prepared->data();
		$data['content']['content']['value']['zxx'] = 'Edited';
		$before = $this->db()->execute('SELECT clock_timestamp() AS time')->one()['time'];
		$store->save($node, $data, $this->context->locales(), Actor::system());
		$edited = $this->storedNode('writer-history');

		$this->assertSame($creator->id, $edited['creator']);
		$this->assertSame($row['created'], $edited['created']);
		$this->assertSame(Actor::system()->id, $edited['editor']);
		$this->assertGreaterThanOrEqual(new DateTimeImmutable($before), new DateTimeImmutable($edited['changed']));
		$history = $this->db()->execute(
			'SELECT editor, changed, content FROM cms.nodes_history WHERE node = :node',
			['node' => $row['node']],
		)->all();
		$this->assertCount(1, $history);
		$this->assertSame($editor->id, $history[0]['editor']);
		$this->assertSame($row['changed'], $history[0]['changed']);
		$this->assertSame('Imported', json_decode($history[0]['content'], true)['content']['value']['zxx']);
	}

	public function testCreatorOverrideLeavesTheDefaultEditorAndDatesAlone(): void
	{
		$creator = new Actor($this->createTestUser(['uid' => 'writer-creator-only']));
		$writer = $this->writer();
		$writer->create($writer->prepare(PlainBlock::class)->uid('writer-creator-only'), creator: $creator);
		$row = $this->storedNode('writer-creator-only');

		$this->assertSame($creator->id, $row['creator']);
		$this->assertSame(Actor::system()->id, $row['editor']);
		$this->assertTrue($row['created_now']);
		$this->assertTrue($row['changed_now']);
	}

	#[DataProvider('dateFields')]
	public function testEachHistoricalDateCanBeProvidedIndependently(string $field, string $default): void
	{
		$date = new DateTimeImmutable('2018-06-07T08:09:10Z');
		$writer = $this->writer();
		$writer->create($writer->prepare(PlainBlock::class)->uid('writer-one-date'), ...[$field => $date]);
		$row = $this->storedNode('writer-one-date');

		$this->assertEquals($date, new DateTimeImmutable($row[$field]));
		$this->assertTrue($row[$default . '_now']);
	}

	public static function dateFields(): array
	{
		return ['created' => ['created', 'changed'], 'changed' => ['changed', 'created']];
	}

	public function testAnUnknownCreatorCannotLeaveAPartialImport(): void
	{
		$writer = $this->writer();
		$prepared = $writer
			->prepare(PlainPage::class, ['heading' => 'Invalid author'])
			->uid('writer-invalid-creator')
			->path('en', '/invalid-creator');

		try {
			$writer->create($prepared, creator: new Actor(PHP_INT_MAX));
			$this->fail('An unknown creator must be rejected');
		} catch (RuntimeException $e) {
			$this->assertSame(23503, $e->getCode());
		}

		$this->assertNull(
			$this->db()->execute(
				'SELECT node FROM cms.nodes WHERE uid = :uid',
				['uid' => 'writer-invalid-creator'],
			)->first(),
		);
		$this->assertNull($this->activePath('writer-invalid-creator'));
	}

	public function testActorRequiresPositiveId(): void
	{
		$this->expectException(ValueError::class);

		new Actor(0);
	}

	public function testExplicitPathIsPreserved(): void
	{
		$writer = $this->writer();
		$prepared = $writer
			->prepare(PlainPage::class, ['heading' => 'Fees'])
			->uid('writer-explicit-path')
			->path('en', 'gebuehren');

		$writer->create($prepared);

		$this->assertSame('/gebuehren', $this->activePath('writer-explicit-path'));
	}

	public function testPathIsGeneratedWithoutExplicitPath(): void
	{
		$writer = $this->writer();
		$prepared = $writer
			->prepare(PlainPage::class, ['heading' => 'Generated'])
			->uid('writer-generated-path');

		$writer->create($prepared);

		$this->assertSame(
			'/plain-page/writer-generated-path',
			$this->activePath('writer-generated-path'),
		);
	}

	public function testExplicitPathCollisionIsRejected(): void
	{
		$writer = $this->writer();
		$writer->create(
			$writer
				->prepare(PlainPage::class, ['heading' => 'First'])
				->uid('writer-path-first')
				->path('en', '/legacy/page'),
		);

		$this->throws(RuntimeException::class, "The URL path '/legacy/page' is already in use");
		$writer->create(
			$writer
				->prepare(PlainPage::class, ['heading' => 'Second'])
				->uid('writer-path-second')
				->path('en', '/legacy/page'),
		);
	}

	public function testExplicitPathWithUnknownLocaleIsRejected(): void
	{
		$writer = $this->writer();
		$prepared = $writer
			->prepare(PlainPage::class, ['heading' => 'Wrong locale'])
			->path('fr', '/page');

		$this->throws(RuntimeException::class, "Unknown locale 'fr' for the node path '/page'");
		$writer->create($prepared);
	}

	public function testEmptyExplicitPathIsRejected(): void
	{
		$writer = $this->writer();
		$prepared = $writer->prepare(PlainPage::class, ['heading' => 'Empty path']);

		$this->throws(ValueError::class, 'A node path must not be empty');
		$prepared->path('en', '   ');
	}
}
