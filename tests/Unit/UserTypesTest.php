<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Exception\RuntimeException;
use Cosray\Security\Policy;
use Cosray\Tests\Fixtures\User\Customer;
use Cosray\Tests\Fixtures\User\Teacher;
use Cosray\Tests\TestCase;
use Cosray\User;
use Cosray\User\Types;

final class UserTypesTest extends TestCase
{
	public function testTheHandleComesFromTheClassNameOrTheAttribute(): void
	{
		$types = new Types();
		$types->add(Teacher::class);
		$types->add(Customer::class);

		$this->assertSame(['user', 'teacher', 'shop-customer'], $types->handles());
		$this->assertSame(Teacher::class, $types->class('teacher'));
		$this->assertSame('Teacher', $types->label('teacher'));
		$this->assertSame('Customer', $types->label('shop-customer'));
	}

	public function testAnUnregisteredTypeFallsBackToThePlainUser(): void
	{
		$types = new Types();

		$this->assertFalse($types->has('retired'));
		$this->assertSame(User::class, $types->class('retired'));
	}

	public function testRolesAttributeLimitsWhatATypeCanBeGiven(): void
	{
		$types = new Types();
		$types->add(Teacher::class);
		$types->add(Customer::class);
		$policy = Policy::withDefaults();

		$this->assertSame(['superuser', 'admin', 'editor'], array_keys($types->roles('user', $policy)));
		$this->assertSame(['editor'], array_keys($types->roles('teacher', $policy)));
		$this->assertSame([], $types->roles('shop-customer', $policy));
	}

	public function testOnlyUserClassesCanBeRegistered(): void
	{
		$this->throws(RuntimeException::class, 'must extend');

		new Types()->add(Policy::class);
	}
}
