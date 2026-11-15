<?php

declare(strict_types=1);

namespace Cosray\Title;

use Celema\Quma\Database;
use Cosray\Bootstrap;
use Cosray\Cms;
use Cosray\Context;
use Cosray\Locale;
use Cosray\Locales;
use Cosray\Node\Factory;
use Cosray\Node\Types;

/**
 * Re-materializes `nodes.title` for every live node from its stored content —
 * the backfill after the column is added and the recovery tool after imports.
 * Runs in a booted app context because resolving a title needs the node type
 * registry (title field / Contract\Title). It writes only the materialized
 * title, so a rebuild records no edits and leaves `changed` alone.
 */
class Rebuild
{
	private readonly Database $db;
	private readonly Factory $factory;
	private readonly Resolver $resolver;

	public function __construct(
		private readonly Context $context,
		private readonly Cms $cms,
		private readonly Locales $locales,
		Types $types,
	) {
		$this->db = $context->db;
		$this->factory = $cms->nodeFactory();
		$this->resolver = new Resolver($types);
	}

	/**
	 * @return array{nodes: int, dynamic: int, empty: int}
	 */
	public function run(): array
	{
		return $this->rebuild($this->nodeClasses());
	}

	/**
	 * @param array<string, class-string> $classes
	 *
	 * @return array{nodes: int, dynamic: int, empty: int}
	 */
	private function rebuild(array $classes): array
	{
		$nodes = 0;
		$dynamic = 0;
		$empty = 0;

		foreach ($this->db->nodes->allForTitle()->lazy() as $row) {
			$nodes++;
			$uid = (string) $row['uid'];
			$class = $classes[(string) $row['type_handle']] ?? null;
			$decoded = json_decode((string) $row['content'], true);
			$content = is_array($decoded) ? $decoded : [];

			$map = $class === null ? [] : $this->titleMap($class, $uid, $content, $dynamic);

			if ($map === []) {
				$empty++;
			}

			$this->db->nodes->updateTitle(['uid' => $uid, 'title' => json_encode($map)])->run();
		}

		return ['nodes' => $nodes, 'dynamic' => $dynamic, 'empty' => $empty];
	}

	/**
	 * @param class-string $class
	 *
	 * @param array<string, mixed> $content
	 * @return array<string, string>
	 */
	private function titleMap(string $class, string $uid, array $content, int &$dynamic): array
	{
		$descriptor = $this->resolver->descriptor($class);

		return match ($descriptor['kind']) {
			Resolver::KIND_FIELD => $this->resolver->fieldMap($content, $descriptor['field']),
			Resolver::KIND_DYNAMIC => $this->dynamicMap($class, $uid, $content, $dynamic),
			default => [],
		};
	}

	/**
	 * @param class-string $class
	 *
	 * @param array<string, mixed> $content
	 * @return array<string, string>
	 */
	private function dynamicMap(string $class, string $uid, array $content, int &$dynamic): array
	{
		$node = $this->factory->create($class, $this->context, $this->cms, [
			'uid' => $uid,
			'content' => $content,
		]);

		$provider = $this->resolver->provider($node);

		if ($provider === null) {
			return [];
		}

		$dynamic++;

		return $this->resolver->dynamicMap(
			fn(Locale $locale): string => $this->context->withLocale(
				$locale,
				$provider->title(...),
			),
			$this->locales,
		);
	}

	/**
	 * @return array<string, class-string>
	 */
	private function nodeClasses(): array
	{
		$map = [];
		$tag = $this->context->container->tag(Bootstrap::NODE_TAG);

		foreach ($tag->entries() as $handle) {
			$class = $tag->entry($handle)->definition();

			if (is_string($class) && class_exists($class)) {
				$map[$handle] = $class;
			}
		}

		return $map;
	}
}
