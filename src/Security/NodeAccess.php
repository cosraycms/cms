<?php

declare(strict_types=1);

namespace Cosray\Security;

use Celema\Container\Container;
use Cosray\Bootstrap;
use Cosray\Node\Factory;
use Cosray\Node\Types;
use Cosray\Node\Wrapper;
use Cosray\User;

/**
 * Who may read and write which nodes. A node type maps the actions `read`,
 * `create`, `change` and `delete` to permissions through `#[Permission]`;
 * actions it leaves out keep the defaults. `read` covers published,
 * undeleted nodes; any other state takes the type's `change` permission.
 */
final class NodeAccess
{
	public const string READ = 'read';
	public const string CREATE = 'create';
	public const string CHANGE = 'change';
	public const string DELETE = 'delete';

	/** @var array<string, class-string>|null */
	private ?array $registered = null;

	public function __construct(
		private readonly Policy $policy,
		private readonly Types $types,
		private readonly Container $container,
	) {}

	/** @return array{read: string, create: string, change: string, delete: string} */
	public static function defaults(bool $routable): array
	{
		return [
			self::READ => $routable ? 'read-pages' : 'read-nodes',
			self::CREATE => 'edit-nodes',
			self::CHANGE => 'edit-nodes',
			self::DELETE => 'edit-nodes',
		];
	}

	/**
	 * A string `#[Permission]` applies to every action.
	 *
	 * @param class-string $class
	 */
	public function permission(string $class, string $action): string
	{
		$declared = $this->types->get($class, 'permission');

		if (is_string($declared) && $declared !== '') {
			return $declared;
		}

		$permission = is_array($declared) ? $declared[$action] ?? null : null;

		if (is_string($permission) && $permission !== '') {
			return $permission;
		}

		return self::defaults((bool) $this->types->get($class, 'routable', false))[$action] ?? 'edit-nodes';
	}

	/** @param class-string $class */
	public function allows(?User $user, string $action, string $class): bool
	{
		return $this->policy->permits($user, $this->permission($class, $action));
	}

	/**
	 * Whether the website serves the node at its path to this user. It serves
	 * any published node with a path, unless the type declares a read
	 * permission, which then applies as in the API, even when it names the
	 * default permission.
	 */
	public function serves(?User $user, object $node): bool
	{
		$class = Wrapper::unwrap($node)::class;

		return !$this->declaresRead($class) || $this->allows($user, self::READ, $class);
	}

	public function reads(?User $user, object $node): bool
	{
		$inner = Wrapper::unwrap($node);

		return $this->allows($user, self::live($inner) ? self::READ : self::CHANGE, $inner::class);
	}

	/**
	 * Handles of the registered types whose live nodes the user reads, and
	 * of those whose nodes the user reads in any state.
	 *
	 * @return array{live: list<string>, any: list<string>}
	 */
	public function readable(?User $user): array
	{
		$live = [];
		$any = [];

		foreach ($this->registered() as $handle => $class) {
			if ($this->allows($user, self::CHANGE, $class)) {
				$any[] = $handle;
			} elseif ($this->allows($user, self::READ, $class)) {
				$live[] = $handle;
			}
		}

		return ['live' => $live, 'any' => $any];
	}

	/** @return array<string, class-string> registered node classes by type handle */
	public function registered(): array
	{
		if ($this->registered !== null) {
			return $this->registered;
		}

		$registered = [];
		$tag = $this->container->tag(Bootstrap::NODE_TAG);

		foreach ($tag->entries() as $handle) {
			$class = $tag->entry($handle)->definition();

			if (is_string($class) && class_exists($class)) {
				$registered[$handle] = $class;
			}
		}

		return $this->registered = $registered;
	}

	/** @param class-string $class */
	private function declaresRead(string $class): bool
	{
		if (!$this->types->schemaOf($class)->declares('permission')) {
			return false;
		}

		$declared = $this->types->get($class, 'permission');

		return is_string($declared) && $declared !== '' || is_array($declared) && ($declared[self::READ] ?? '') !== '';
	}

	private static function live(object $node): bool
	{
		return (bool) Factory::meta($node, 'published') && Factory::meta($node, 'deleted') === null;
	}
}
