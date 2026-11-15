<?php

declare(strict_types=1);

namespace Cosray\Node;

use Celema\Container\Container;
use Celema\Quma\Database;
use Cosray\Bootstrap;
use Cosray\Exception\RuntimeException;

/**
 * Keeps the `publications` rows in line with the registered node types:
 * a node of a type with publication has one, a record has none. Records
 * are known only in PHP, so a type that turns into or out of a record
 * needs its rows deleted or created.
 */
final class Publications
{
	/** @param array<string, class-string> $registered node classes by type handle */
	public function __construct(
		private readonly Database $db,
		private readonly Types $types,
		private readonly array $registered,
	) {}

	public static function of(Container $container, Database $db): self
	{
		$types = $container->get(Types::class);
		$registered = [];

		if (!$types instanceof Types) {
			throw new RuntimeException('The node types are not registered');
		}

		$tag = $container->tag(Bootstrap::NODE_TAG);

		foreach ($tag->entries() as $handle) {
			$class = $tag->entry($handle)->definition();

			if (is_string($class) && class_exists($class)) {
				$registered[$handle] = $class;
			}
		}

		return new self($db, $types, $registered);
	}

	/** @return list<string> handles of the registered record types */
	public function records(): array
	{
		$records = [];

		foreach ($this->registered as $handle => $class) {
			if ((bool) $this->types->get($class, 'record', false)) {
				$records[] = $handle;
			}
		}

		sort($records);

		return $records;
	}

	/** @return list<string> handles of the registered types without drafts, records among them */
	public function undrafted(): array
	{
		$handles = [];

		foreach ($this->registered as $handle => $class) {
			if (!(bool) $this->types->get($class, 'drafts', true)) {
				$handles[] = $handle;
			}
		}

		sort($handles);

		return $handles;
	}

	/** @return list<string> handles of the registered types with publication */
	public function publishable(): array
	{
		$handles = array_values(array_diff(array_keys($this->registered), $this->records()));
		sort($handles);

		return $handles;
	}

	/**
	 * What does not match the types: rows of record types, nodes of other
	 * registered types without a row, and working copies left on types
	 * without drafts, which their editor no longer shows.
	 *
	 * @return array{stale: list<array{uid: string, type: string}>, missing: list<array{uid: string, type: string}>, drafts: list<array{uid: string, type: string}>}
	 */
	public function check(): array
	{
		$rows = static fn(iterable $rows): array => array_values(array_map(
			static fn(array $row): array => ['uid' => (string) $row['uid'], 'type' => (string) $row['type']],
			[...$rows],
		));

		return [
			'stale' => $rows($this->db->publications->stale(['types' => $this->list($this->records())])->all()),
			'missing' => $rows($this->db->publications->missing(['types' => $this->list($this->publishable())])->all()),
			'drafts' => $rows($this->db->publications->drafts(['types' => $this->list($this->undrafted())])->all()),
		];
	}

	/**
	 * Deletes the rows of record types and creates the missing rows of the
	 * other registered types, unpublished unless `$publish` says otherwise.
	 *
	 * @return array{deleted: int, created: int}
	 */
	public function sync(bool $publish = false): array
	{
		return $this->db->transaction(fn(): array => [
			'deleted' => count(
				$this->db->publications->deleteStale(['types' => $this->list($this->records())])->all(),
			),
			'created' => count(
				$this->db
					->publications
					->createMissing(['types' => $this->list($this->publishable()), 'published' => $publish])
					->all(),
			),
		]);
	}

	/** @param list<string> $handles */
	private function list(array $handles): string
	{
		return json_encode($handles, JSON_THROW_ON_ERROR);
	}
}
