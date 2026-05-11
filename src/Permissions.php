<?php

declare(strict_types=1);

namespace Celemas\Cms;

class Permissions
{
	protected array $permissions = [
		'superuser' => [
			'superuser',
			'admin',
			'editor',
			'panel',
			'edit-settings',
			'edit-users',
			'edit-pages',
			'edit-blocks',
			'authenticated',
		],
		'admin' => [
			'admin',
			'editor',
			'panel',
			'edit-users',
			'edit-pages',
			'edit-blocks',
			'authenticated',
		],
		'editor' => [
			'editor',
			'panel',
			'edit-pages',
			'edit-blocks',
			'authenticated',
		],
	];

	public function add(string $role, string $permission)
	{
		$this->permissions[$role][] = $permission;
	}

	public function has(string $role, string $permission): bool
	{
		return in_array($permission, $this->permissions[$role] ?? [], true);
	}

	public function get(string $role): array
	{
		return $this->permissions[$role] ?? [];
	}
}
