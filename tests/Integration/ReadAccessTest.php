<?php

declare(strict_types=1);

namespace Cosray\Tests\Integration;

use Celema\Container\Container;
use Cosray\Bootstrap;
use Cosray\Cms;
use Cosray\Context;
use Cosray\Exception\ReadDenied;
use Cosray\Field\Services;
use Cosray\Locales;
use Cosray\Node\Types;
use Cosray\Node\View;
use Cosray\Tests\Fixtures\Node\RestrictedPage;
use Cosray\Tests\IntegrationTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class ReadAccessTest extends IntegrationTestCase
{
	public function container(): Container
	{
		$container = parent::container();
		$container->tag(Bootstrap::NODE_TAG)->add('restricted-page', RestrictedPage::class);

		return $container;
	}

	protected function setUp(): void
	{
		parent::setUp();
		$private = $this->createTestType('restricted-page');
		$public = $this->createTestType('test-page');
		$this->createTestNode(['uid' => 'private-page', 'handle' => 'private', 'type' => $private]);
		$this->createTestNode(['uid' => 'public-page', 'type' => $public]);
	}

	public function testListingsFilterBeforeCountingAndPagination(): void
	{
		$cms = $this->createCms();
		$finder = $cms->nodes->only('private-page', 'public-page')->order('uid ASC')->limit(1);

		$this->assertSame(1, $finder->count());
		$this->assertSame('public-page', iterator_to_array($finder)[0]->meta->uid);
	}

	public static function reads(): array
	{
		return [['uid'], ['handle'], ['render']];
	}

	#[DataProvider('reads')]
	public function testDirectReadsCannotBypassPermission(string $method): void
	{
		$cms = $this->createCms();
		$this->expectException(ReadDenied::class);

		match ($method) {
			'uid' => $cms->node->byUid('private-page'),
			'handle' => $cms->node->byHandle('private'),
			'render' => (string) $cms->render('private-page'),
		};
	}

	public function testRequestFreeContentWorkCanReadRestrictedNodes(): void
	{
		$locales = new Locales();
		$locales->add('en', title: 'English');
		$context = Context::console($this->db(), $this->config(), $this->container(), $this->factory(), $locales);
		$cms = new Cms($context, Services::withDefaults());

		$this->assertSame('private-page', $cms->node->byUid('private-page')->meta->uid);
		$this->assertSame(2, $cms->nodes->only('private-page', 'public-page')->count());
	}

	public function testRenderingAnAlreadyHydratedNodeStillChecksAccess(): void
	{
		$context = $this->createContext();
		$cms = new Cms($context, Services::withDefaults());
		$view = new View(new RestrictedPage(), $cms, $context, new Types());
		$this->expectException(ReadDenied::class);
		$view->output();
	}
}
