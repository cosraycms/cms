<?php

declare(strict_types=1);

namespace Cosray\User;

use Cosray\Exception\RuntimeException;
use Cosray\Schema\Handle;
use Cosray\Schema\Label;
use Cosray\Schema\Roles;
use Cosray\Security\Policy;
use Cosray\User;
use ReflectionClass;

final class Types
{
	public const string DEFAULT = 'user';

	/** @var array<string, class-string<User>> */
	private array $classes = [self::DEFAULT => User::class];

	/**
	 * A class whose handle is already taken replaces the earlier one, which
	 * is how an app gives the default user type fields of its own.
	 *
	 * @param class-string<User> $class
	 */
	public function add(string $class): void
	{
		if (!is_a($class, User::class, true)) {
			throw new RuntimeException('User types must extend ' . User::class . ": {$class}");
		}

		$this->classes[self::handleOf($class)] = $class;
	}

	/** @return list<string> */
	public function handles(): array
	{
		return array_keys($this->classes);
	}

	public function has(string $handle): bool
	{
		return isset($this->classes[$handle]);
	}

	/**
	 * A stored type the app no longer registers falls back to the plain
	 * user, so the account stays usable and editable.
	 *
	 * @return class-string<User>
	 */
	public function class(string $handle): string
	{
		return $this->classes[$handle] ?? User::class;
	}

	public function label(string $handle): string
	{
		$class = $this->class($handle);
		$label = new ReflectionClass($class)->getAttributes(Label::class)[0] ?? null;

		return $label?->newInstance()->label ?? new ReflectionClass($class)->getShortName();
	}

	/** @return array<string, string> role name → label */
	public function roles(string $handle, Policy $policy): array
	{
		$roles = $policy->roles();
		$limit = new ReflectionClass($this->class($handle))->getAttributes(Roles::class)[0] ?? null;

		if ($limit === null) {
			return $roles;
		}

		return array_intersect_key($roles, array_flip($limit->newInstance()->roles));
	}

	/** @param class-string<User> $class */
	private static function handleOf(string $class): string
	{
		$reflection = new ReflectionClass($class);
		$handle = $reflection->getAttributes(Handle::class)[0] ?? null;

		if ($handle !== null) {
			return $handle->newInstance()->value;
		}

		return ltrim(strtolower((string) preg_replace('/[A-Z]/', '-$0', $reflection->getShortName())), '-');
	}
}
