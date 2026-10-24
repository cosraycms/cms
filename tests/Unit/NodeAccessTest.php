<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Celema\Container\Container;
use Cosray\Bootstrap;
use Cosray\Node\Types;
use Cosray\Security\NodeAccess;
use Cosray\Security\Policy;
use Cosray\Tests\Fixtures\Node\NodeWithNameAttribute;
use Cosray\Tests\Fixtures\Node\NodeWithPermissionAttribute;
use Cosray\Tests\Fixtures\Node\NodeWithRouteAttribute;
use Cosray\Tests\TestCase;
use Cosray\User;

final class NodeAccessTest extends TestCase
{
	public function testAnyoneReadsLiveNodesOfRoutableTypesOnly(): void
	{
		$access = $this->access();

		$this->assertTrue($access->allows(null, NodeAccess::READ, NodeWithRouteAttribute::class));
		$this->assertFalse($access->allows(null, NodeAccess::READ, NodeWithNameAttribute::class));
		$this->assertFalse($access->allows(null, NodeAccess::CHANGE, NodeWithRouteAttribute::class));
	}

	public function testEditorsReadEveryTypeAndWriteNodes(): void
	{
		$access = $this->access();
		$editor = $this->user(['editor']);

		$this->assertTrue($access->allows($editor, NodeAccess::READ, NodeWithNameAttribute::class));
		$this->assertTrue($access->allows($editor, NodeAccess::CREATE, NodeWithNameAttribute::class));
		$this->assertTrue($access->allows($editor, NodeAccess::DELETE, NodeWithRouteAttribute::class));
		$this->assertFalse($access->allows($this->user([]), NodeAccess::CHANGE, NodeWithRouteAttribute::class));
	}

	public function testATypeOverridesSomeActionsAndKeepsTheOtherDefaults(): void
	{
		$access = $this->access();

		$this->assertSame('me', $access->permission(NodeWithPermissionAttribute::class, NodeAccess::READ));
		$this->assertSame('edit-nodes', $access->permission(NodeWithPermissionAttribute::class, NodeAccess::CHANGE));
	}

	public function testReadableSplitsTypesByTheStatesAUserReads(): void
	{
		$access = $this->access();

		$this->assertSame(['live' => ['page'], 'any' => []], $access->readable(null));
		$this->assertSame(['live' => [], 'any' => ['page', 'record']], $access->readable($this->user(['editor'])));
	}

	private function access(): NodeAccess
	{
		$container = new Container();
		$container->tag(Bootstrap::NODE_TAG)->add('page', NodeWithRouteAttribute::class);
		$container->tag(Bootstrap::NODE_TAG)->add('record', NodeWithNameAttribute::class);

		return new NodeAccess(Policy::withDefaults(), new Types(), $container);
	}

	/** @param list<string> $roles */
	private function user(array $roles): User
	{
		return new User([
			'usr' => 7,
			'uid' => 'access-user',
			'type' => 'user',
			'username' => 'access',
			'email' => 'access@example.com',
			'password' => '',
			'roles' => json_encode($roles),
			'active' => true,
			'panel_locale' => null,
			'data' => '{}',
			'creator' => 'system',
			'editor' => 'system',
			'created' => '2026-10-24',
			'changed' => '2026-10-24',
			'deleted' => null,
		]);
	}
}
