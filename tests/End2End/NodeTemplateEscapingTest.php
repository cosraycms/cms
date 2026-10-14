<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Cosray\Field\Text;
use Cosray\Tests\End2EndTestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class NodeTemplateEscapingTest extends End2EndTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixtures('basic-types');
	}

	public function testPageTemplatesEscapeNodeTextOnce(): void
	{
		$type = $this->db()->execute('SELECT type FROM cms.types WHERE handle = :handle', [
			'handle' => 'test-page',
		])->one()['type'];
		$nodeId = $this->createTestNode([
			'uid' => 'escaping-page',
			'type' => (int) $type,
			'published' => true,
			'content' => ['title' => ['type' => Text::class, 'value' => ['en' => 'Tom & <Jerry>']]],
		]);
		$this->createTestPath($nodeId, '/escaping');

		$response = $this->makeRequest('GET', '/escaping');

		$this->assertResponseOk($response);
		$html = $this->getHtmlResponse($response);
		// The title tag prints the field value, the heading the node's title().
		$this->assertStringContainsString('<title>Tom &amp; &lt;Jerry&gt;</title>', $html);
		$this->assertStringContainsString('<h1>Tom &amp; &lt;Jerry&gt;</h1>', $html);
	}
}
