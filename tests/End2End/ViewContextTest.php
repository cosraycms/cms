<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Cosray\Bootstrap;
use Cosray\Config;
use Cosray\Field\Text;
use Cosray\Tests\End2EndTestCase;
use Cosray\Tests\Fixtures\Node\ViewContextHost;
use Cosray\Tests\Fixtures\Node\ViewContextPage;

/**
 * End-to-end tests for the ViewContext hook.
 *
 * A node prepares its own template variables, on both render paths: as the
 * page for its public path, and embedded through `$cms->render()`.
 *
 * @internal
 *
 * @coversNothing
 */
final class ViewContextTest extends End2EndTestCase
{
	protected function setUp(): void
	{
		parent::setUp();

		$this->loadFixtures('basic-types');
	}

	protected function createBootstrap(Config $config): Bootstrap
	{
		$bootstrap = parent::createBootstrap($config);
		$bootstrap->node(ViewContextPage::class);
		$bootstrap->node(ViewContextHost::class);

		return $bootstrap;
	}

	public function testContextReachesThePageTemplate(): void
	{
		$this->createNode('view-context-page', 'view-context-page', 'Contexted');
		$this->createTestPath($this->nodeId, '/view-context');

		$response = $this->makeRequest('GET', '/view-context');

		$this->assertResponseOk($response);
		$this->assertSame(
			'[hello Contexted|view-context-page]',
			trim($this->getHtmlResponse($response)),
		);
	}

	public function testContextAlsoReachesAnEmbeddedRender(): void
	{
		$this->createNode('view-context-embedded', 'view-context-page', 'Embedded');
		$this->createNode('view-context-host', 'view-context-host', 'Host');
		$this->createTestPath($this->nodeId, '/view-context-host');

		$response = $this->makeRequest('GET', '/view-context-host');

		$this->assertResponseOk($response);
		$this->assertSame(
			'host:[hello Embedded|view-context-embedded]',
			trim($this->getHtmlResponse($response)),
		);
	}

	private int $nodeId = 0;

	private function createNode(string $uid, string $handle, string $title): void
	{
		$type = $this->db()->execute(
			'SELECT type FROM cms.types WHERE handle = :handle',
			['handle' => $handle],
		)->first();
		$typeId = $type ? (int) $type['type'] : $this->createTestType($handle);

		$this->nodeId = $this->createTestNode([
			'uid' => $uid,
			'type' => $typeId,
			'published' => true,
			'content' => [
				'title' => ['type' => Text::class, 'value' => ['en' => $title]],
			],
		]);
	}
}
