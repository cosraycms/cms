<?php

declare(strict_types=1);

namespace Celemas\Cms;

class User
{
	public readonly int $id;
	public readonly string $uid;
	public readonly string $username;
	public readonly string $email;
	public readonly string $pwhash;
	public readonly string $role;
	public readonly bool $active;
	public readonly string $created;
	public readonly string $changed;
	public readonly ?string $deleted;
	public readonly ?string $expires;

	public function __construct(
		protected readonly array $data,
	) {
		$this->id = $data['usr'];
		$this->uid = $data['uid'];
		$this->username = $data['username'] ?? '';
		$this->email = $data['email'];
		$this->pwhash = $data['pwhash'];
		$this->role = $data['role'];
		$this->active = $data['active'];
		$this->created = $data['created'];
		$this->changed = $data['changed'];
		$this->deleted = $data['deleted'];
		$this->expires = $data['expires'] ?? null;
	}

	public function hasPermission(string $permission): bool
	{
		$permissions = new Permissions();

		return $permissions->has($this->role, $permission);
	}

	public function permissions(): array
	{
		$permissions = new Permissions();

		return $permissions->get($this->role);
	}

	public function array(): array
	{
		return array_filter(
			$this->data,
			static fn($key) => $key !== 'pwhash',
			ARRAY_FILTER_USE_KEY,
		);
	}
}
