<?php

declare(strict_types=1);

namespace Cosray\Plugin;

use Cosray\Config\Database;
use Cosray\Exception\RuntimeException;

/**
 * The database namespace of each plugin that has migrations or SQL: where
 * its tables live, apart from Cosray's and from other plugins'.
 *
 * A plugin's SQL names its tables through `{id}.prefix` and its indexes
 * and triggers through `{id}.obj`. By default the namespace is the plugin
 * id in snake case, in the form Cosray's own has: a schema on PostgreSQL
 * (`acme_shop.`), or a part of each name (`acme_shop_`) on other drivers
 * and where the site gave Cosray such a prefix. A site sets another one in
 * `db.placeholders`.
 *
 * @internal
 */
final class Namespaces
{
	/** @var array<string, array{prefix: string, obj: string}> */
	private array $namespaces = [];

	private readonly string $driver;

	/** @param list<string> $plugins the ids of the plugins with migrations or SQL */
	public function __construct(
		private readonly Database $db,
		array $plugins,
	) {
		$this->driver = $db->driver();

		foreach ($plugins as $id) {
			$this->namespaces[$id] = $this->namespace($id);
		}

		$this->assertApart();
	}

	/**
	 * The site's placeholders with those of the plugins.
	 *
	 * @return array<non-empty-string, array<non-empty-string, string>>
	 */
	public function placeholders(): array
	{
		$placeholders = $this->db->placeholders;

		foreach ($this->namespaces as $id => $namespace) {
			$placeholders[$this->driver]["{$id}.prefix"] = $namespace['prefix'];
			$placeholders[$this->driver]["{$id}.obj"] = $namespace['obj'];
		}

		/** @var array<non-empty-string, array<non-empty-string, string>> */
		return $placeholders;
	}

	/** The prefix of a plugin's tables, null for a plugin without migrations and SQL. */
	public function prefix(string $id): ?string
	{
		return $this->namespaces[$id]['prefix'] ?? null;
	}

	/** @return array{prefix: string, obj: string} */
	private function namespace(string $id): array
	{
		$prefix = $this->configured("{$id}.prefix") ?? $this->default($id);

		if (
			$prefix === ''
			|| $prefix === 'public.'
			|| !$this->db->validPrefix($prefix, $this->driver)
		) {
			throw new RuntimeException(
				"Invalid database namespace '{$prefix}' for plugin {$id}: set '{$id}.prefix' in db.placeholders"
					. " to a schema, like 'acme_shop.', or to a table name prefix, like 'acme_shop_'.",
			);
		}

		$schema = str_ends_with($prefix, '.');

		return ['prefix' => $prefix, 'obj' => $this->configured("{$id}.obj") ?? ($schema ? '' : $prefix)];
	}

	private function configured(string $name): ?string
	{
		$placeholders = $this->db->placeholders;
		$value = $placeholders[$this->driver][$name] ?? $placeholders['all'][$name] ?? null;

		return is_string($value) ? $value : null;
	}

	/** The id in snake case, as a schema where Cosray's tables have one, or no namespace yet. */
	private function default(string $id): string
	{
		$name = str_replace('-', '_', $id);
		$cms = $this->db->prefix($this->driver);

		return $this->driver === 'pgsql' && ($cms === '' || str_ends_with($cms, '.')) ? "{$name}." : "{$name}_";
	}

	/**
	 * Two namespaces are the same when their prefixes are equal, and also
	 * when one name prefix starts with the other: `cms_shop_orders` would
	 * read as a table of `cms_`.
	 */
	private function assertApart(): void
	{
		$owners = ['Cosray' => $this->db->prefix($this->driver)];

		foreach ($this->namespaces as $id => $namespace) {
			foreach ($owners as $owner => $taken) {
				if ($taken !== '' && self::overlap($namespace['prefix'], $taken)) {
					throw new RuntimeException(
						"The database namespace '{$namespace['prefix']}' of plugin {$id} overlaps '{$taken}' of {$owner}:"
							. " set another '{$id}.prefix' in db.placeholders.",
					);
				}
			}

			$owners["plugin {$id}"] = $namespace['prefix'];
		}
	}

	private static function overlap(string $one, string $other): bool
	{
		if ($one === $other) {
			return true;
		}

		if (str_ends_with($one, '.') || str_ends_with($other, '.')) {
			return false;
		}

		return str_starts_with($one, $other) || str_starts_with($other, $one);
	}
}
