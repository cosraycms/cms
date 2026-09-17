<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Security\Policy;
use Cosray\Tests\TestCase;
use Cosray\User;

final class PolicyTest extends TestCase
{
	public function testAnonymousHoldsOnlyWhatEveryoneIsAllowed(): void
	{
		$policy = Policy::withDefaults();
		$policy->allow(Policy::EVERYONE, 'view');
		$policy->allow(Policy::AUTHENTICATED, 'comment');

		$this->assertTrue($policy->permits(null, 'view'));
		$this->assertFalse($policy->permits(null, 'comment'));
		$this->assertFalse($policy->permits(null, 'panel'));
	}

	public function testAUserWithoutRolesIsStillAuthenticated(): void
	{
		$policy = Policy::withDefaults();
		$policy->allow(Policy::AUTHENTICATED, 'comment');
		$policy->allow('type:customer', 'order');
		$customer = $this->user([], 'customer');

		$this->assertTrue($policy->permits($customer, 'comment'));
		$this->assertTrue($policy->permits($customer, 'order'));
		$this->assertFalse($policy->permits($customer, 'panel'));
		$this->assertFalse($policy->permits($this->user([]), 'order'));
	}

	public function testSeveralRolesGrantTheUnionOfTheirPermissions(): void
	{
		$policy = Policy::withDefaults();
		$policy->role('shop', 'Shop manager');
		$policy->allow('role:shop', 'edit-orders');
		$user = $this->user(['editor', 'shop']);

		$this->assertTrue($policy->permits($user, 'edit-nodes'));
		$this->assertTrue($policy->permits($user, 'edit-orders'));
		$this->assertFalse($policy->permits($user, 'edit-users'));
	}

	public function testAStoredRoleTheAppDoesNotDefineGrantsNothing(): void
	{
		$policy = Policy::withDefaults();
		$policy->allow('role:retired', 'panel');

		$this->assertFalse($policy->permits($this->user(['retired']), 'panel'));
	}

	public function testOnlySuperusersManageMenusAndSettings(): void
	{
		$policy = Policy::withDefaults();

		$this->assertTrue($policy->permits($this->user(['superuser']), 'manage-menus'));
		$this->assertTrue($policy->permits($this->user(['superuser']), 'edit-settings'));
		$this->assertFalse($policy->permits($this->user(['admin']), 'manage-menus'));
		$this->assertTrue($policy->permits($this->user(['admin']), 'edit-users'));
		$this->assertFalse($policy->permits($this->user(['editor']), 'edit-menus'));
	}

	public function testNobodyReachesBeyondTheirOwnPermissions(): void
	{
		$policy = Policy::withDefaults();
		$admin = $this->user(['admin']);

		$this->assertTrue($policy->covers($admin, 'editor', 'admin'));
		$this->assertFalse($policy->covers($admin, 'superuser'));
		$this->assertTrue($policy->covers($this->user(['superuser']), 'admin', 'editor'));
		$this->assertTrue($policy->covers($admin, 'retired'));
	}

	/** @param list<string> $roles */
	private function user(array $roles, string $type = 'user'): User
	{
		return new User([
			'usr' => 42,
			'uid' => 'someone',
			'type' => $type,
			'email' => 'someone@example.com',
			'password' => 'hash',
			'roles' => $roles,
			'active' => true,
			'created' => '2024-01-01T00:00:00+00:00',
			'changed' => '2024-01-01T00:00:00+00:00',
			'deleted' => null,
		]);
	}
}
