<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Cosray\Bootstrap;
use Cosray\Config;
use Cosray\Field\Text;
use Cosray\Tests\End2EndTestCase;
use Cosray\Tests\Fixtures\Node\ChildAwarePage;

/**
 * End-to-end tests for the node-bound view service and the wrapper the
 * renderer hands to templates.
 *
 * @internal
 *
 * @coversNothing
 */
final class NodeViewTest extends End2EndTestCase
{
	private int $parentId = 0;

	protected function setUp(): void
	{
		parent::setUp();

		$this->loadFixtures('basic-types');
		$this->createTree();
	}

	protected function createBootstrap(Config $config): Bootstrap
	{
		$bootstrap = parent::createBootstrap($config);
		$bootstrap->node(ChildAwarePage::class);

		return $bootstrap;
	}

	public function testRenderedWrapperCanListChildren(): void
	{
		// Before the renderer built proxies through the node factory this
		// raised "children() is only available on finder-backed node proxies".
		$response = $this->makeRequest('GET', '/child-aware');

		$this->assertResponseOk($response);
		$this->assertSame('children:2|note:default', trim($this->getHtmlResponse($response)));
	}

	public function testViewServiceRendersTheNodeWithExtraContext(): void
	{
		$response = $this->makeRequest('POST', '/child-aware');

		$this->assertResponseOk($response);
		// childCount still comes from viewContext(), note from render().
		$this->assertSame('children:2|note:posted', trim($this->getHtmlResponse($response)));
	}

	private function createTree(): void
	{
		$typeId = $this->createTestType('child-aware-page');
		$this->parentId = $this->createTestNode([
			'uid' => 'child-aware-parent',
			'type' => $typeId,
			'published' => true,
			'content' => ['title' => ['type' => Text::class, 'value' => ['en' => 'Parent']]],
		]);
		$this->createTestPath($this->parentId, '/child-aware');

		foreach (['child-aware-one', 'child-aware-two'] as $uid) {
			$this->createTestNode([
				'uid' => $uid,
				'type' => $typeId,
				'parent' => $this->parentId,
				'published' => true,
				'content' => ['title' => ['type' => Text::class, 'value' => ['en' => $uid]]],
			]);
		}
	}
}
