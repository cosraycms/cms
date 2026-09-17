<?php

declare(strict_types=1);

namespace Cosray\Security;

use Cosray\User;

final class Policy
{
	public const string EVERYONE = 'everyone';
	public const string AUTHENTICATED = 'authenticated';

	/** @var array<string, string> */
	private array $roles = [];

	public function __construct(
		private readonly Acl $acl = new Acl(),
	) {}

	public static function withDefaults(): self
	{
		$policy = new self();

		$policy->role('superuser', 'Superuser');
		$policy->role('admin', 'Administrator');
		$policy->role('editor', 'Editor');

		$policy->allow(
			'role:superuser',
			'panel',
			'edit-nodes',
			'edit-menus',
			'manage-menus',
			'edit-users',
			'edit-settings',
		);
		$policy->allow('role:admin', 'panel', 'edit-nodes', 'edit-menus', 'edit-users');
		$policy->allow('role:editor', 'panel', 'edit-nodes');

		return $policy;
	}

	public function role(string $name, string $label): void
	{
		$this->roles[$name] = $label;
	}

	/** @return array<string, string> */
	public function roles(): array
	{
		return $this->roles;
	}

	public function allow(string $principal, string ...$permissions): void
	{
		$this->acl->allow($principal, ...$permissions);
	}

	/**
	 * A stored role the app no longer defines grants nothing.
	 *
	 * @return list<string>
	 */
	public function principals(?User $user): array
	{
		if ($user === null) {
			return [self::EVERYONE];
		}

		$principals = [self::EVERYONE, self::AUTHENTICATED, "user:{$user->uid}", "type:{$user->type}"];

		foreach ($user->roles as $role) {
			if (isset($this->roles[$role])) {
				$principals[] = "role:{$role}";
			}
		}

		return $principals;
	}

	public function permits(?User $user, string $permission): bool
	{
		return $this->acl->permits($this->principals($user), $permission);
	}
}
