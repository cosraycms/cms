<?php

declare(strict_types=1);

namespace Cosray\Tests\Integration;

use Cosray\Actor;
use Cosray\Cms;
use Cosray\Context;
use Cosray\Exception\RuntimeException;
use Cosray\Field\Services;
use Cosray\Locales;
use Cosray\Node\Writer;
use Cosray\Tests\Fixtures\Node\PlainBlock;
use Cosray\Tests\Fixtures\Node\PlainPage;
use Cosray\Tests\IntegrationTestCase;
use ValueError;

/**
 * @internal
 *
 * @coversNothing
 */
final class NodeWriterTest extends IntegrationTestCase
{
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

		return new Writer($context, new Cms($context, $services), $services->types);
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
		$draft = $writer
			->draft(PlainBlock::class, ['content' => 'Console content'])
			->uid('writer-console-node')
			->published()
			->fieldMeta('content', 'source', ['zxx' => 'console']);

		$result = $writer->create($draft);
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

	public function testActorRequiresPositiveId(): void
	{
		$this->expectException(ValueError::class);

		new Actor(0);
	}

	public function testExplicitPathIsPreserved(): void
	{
		$writer = $this->writer();
		$draft = $writer
			->draft(PlainPage::class, ['heading' => 'Fees'])
			->uid('writer-explicit-path')
			->path('en', 'gebuehren');

		$writer->create($draft);

		$this->assertSame('/gebuehren', $this->activePath('writer-explicit-path'));
	}

	public function testPathIsGeneratedWithoutExplicitPath(): void
	{
		$writer = $this->writer();
		$draft = $writer
			->draft(PlainPage::class, ['heading' => 'Generated'])
			->uid('writer-generated-path');

		$writer->create($draft);

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
				->draft(PlainPage::class, ['heading' => 'First'])
				->uid('writer-path-first')
				->path('en', '/legacy/page'),
		);

		$this->throws(RuntimeException::class, "The URL path '/legacy/page' is already in use");
		$writer->create(
			$writer
				->draft(PlainPage::class, ['heading' => 'Second'])
				->uid('writer-path-second')
				->path('en', '/legacy/page'),
		);
	}

	public function testExplicitPathWithUnknownLocaleIsRejected(): void
	{
		$writer = $this->writer();
		$draft = $writer
			->draft(PlainPage::class, ['heading' => 'Wrong locale'])
			->path('fr', '/page');

		$this->throws(RuntimeException::class, "Unknown locale 'fr' for the node path '/page'");
		$writer->create($draft);
	}

	public function testEmptyExplicitPathIsRejected(): void
	{
		$writer = $this->writer();
		$draft = $writer->draft(PlainPage::class, ['heading' => 'Empty path']);

		$this->throws(ValueError::class, 'A node path must not be empty');
		$draft->path('en', '   ');
	}
}
