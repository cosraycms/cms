<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Celema\Container\Container;
use Cosray\Block\Registry;
use Cosray\Config;
use Cosray\Context;
use Cosray\Node\FieldOwner;
use Cosray\Tests\Fixtures\Block\RequestBlock;
use Cosray\Tests\Fixtures\Context\RequestAware;
use Cosray\Tests\Fixtures\Context\RequestService;
use Cosray\Tests\TestCase;

/**
 * Objects Cosray builds for a request (nodes, embedded objects, collections,
 * block types, dashboard cards) get the same constructor arguments.
 *
 * @internal
 *
 * @coversNothing
 */
final class ContextCreateTest extends TestCase
{
	public function testCreatedObjectsReceiveTheRequestContext(): void
	{
		[$context, $scope] = $this->requestContext();

		$object = $context->create(RequestAware::class);

		$this->assertSame($context, $object->context);
		$this->assertSame($context->request, $object->request);
		$this->assertSame($context->config, $object->config);
		$this->assertSame($context->db, $object->db);
		$this->assertSame($context->factory, $object->factory);
		$this->assertSame($scope, $object->container);
		$this->assertSame($scope->get(RequestService::class), $object->service);
	}

	public function testGivenTypesWinOverTheContextTypes(): void
	{
		[$context] = $this->requestContext();
		$config = $this->config(['app.name' => 'other']);

		$object = $context->create(RequestAware::class, [Config::class => $config]);

		$this->assertSame($config, $object->config);
	}

	public function testBlockTypesAreBuiltForTheRequestTheyRenderIn(): void
	{
		[$context, $scope] = $this->requestContext();
		$owner = new FieldOwner($context, 'node-uid');

		$block = Registry::withDefaults()->create(RequestBlock::class, $owner);

		$this->assertInstanceOf(RequestBlock::class, $block);
		$this->assertSame($owner, $block->owner);
		$this->assertSame($context->request, $block->request);
		$this->assertSame($scope->get(RequestService::class), $block->service);
	}

	/** @return array{Context, Container} */
	private function requestContext(): array
	{
		$root = new Container();
		$root->add(RequestService::class)->scoped();
		$scope = $root->scope();

		return [new Context($this->db(), $this->request(), $this->config(), $scope, $this->factory()), $scope];
	}
}
