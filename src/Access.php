<?php

declare(strict_types=1);

namespace Cosray;

use Cosray\Exception\ReadDenied;
use Cosray\Node\Types;

final class Access
{
	public function __construct(
		private readonly Context $context,
	) {}

	public function allows(string $permission): bool
	{
		if ($permission === 'everyone') {
			return true;
		}

		$request = $this->context->request;
		if ($request === null) {
			return true;
		}

		$user = $request->get('user', null);
		if (!$user instanceof User) {
			return false;
		}

		return (
			$request->get('cms.management', false) && $user->hasPermission('panel')
				|| $user->hasPermission($permission)
		);
	}

	public function require(string $permission): void
	{
		if (!$this->allows($permission)) {
			throw new ReadDenied($permission);
		}
	}

	public static function permission(string $class, Types $types): string
	{
		$permissions = $types->get($class, 'permission');

		return is_string($permissions) ? $permissions : $permissions['read'] ?? 'everyone';
	}
}
