<?php

declare(strict_types=1);

namespace Cosray\Tests\Integration;

use Cosray\Cms;
use Cosray\Context;
use Cosray\Field\Services;
use Cosray\Locales;
use Cosray\Node\Actor;
use Cosray\Node\Writer;
use Cosray\Tests\Fixtures\Node\PlainBlock;
use Cosray\Tests\IntegrationTestCase;
use ValueError;

/**
 * @internal
 *
 * @coversNothing
 */
final class NodeWriterTest extends IntegrationTestCase
{
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
}
