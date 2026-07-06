<?php

declare(strict_types=1);

namespace Cosray\Title;

use Celemas\Core\Request;
use Celemas\Quma\Connection;
use Celemas\Quma\Database;
use Cosray\Bootstrap;
use Cosray\Cms;
use Cosray\Context;
use Cosray\Locale;
use Cosray\Locales;
use Cosray\Node\Contract\Title as TitleContract;
use Cosray\Node\Factory;
use Cosray\Node\Types;

/**
 * Re-materializes `nodes.title` for every live node from its stored content —
 * the backfill after the column is added and the recovery tool after imports.
 * Runs in a booted app context because resolving a title needs the node type
 * registry (title field / Contract\Title). The change and history triggers are
 * suspended so a rebuild is not recorded as a wave of edits.
 */
class Rebuild
{
	private const array TRIGGERS = ['nodes_trigger_02_change', 'nodes_trigger_03_history'];

	private readonly Database $db;
	private readonly Request $request;
	private readonly Factory $factory;
	private readonly Resolver $resolver;

	public function __construct(
		private readonly Context $context,
		private readonly Cms $cms,
		private readonly Locales $locales,
		private readonly Connection $conn,
		Types $types,
	) {
		$this->db = $context->db;
		$this->request = $context->request;
		$this->factory = $cms->nodeFactory();
		$this->resolver = new Resolver($types);
	}

	/**
	 * @return array{nodes: int, dynamic: int, empty: int}
	 */
	public function run(): array
	{
		$classes = $this->nodeClasses();

		// Field hydration reads these from the request; a CLI request may not
		// carry them, and the per-locale eval overwrites `locale` anyway.
		$this->request->set('locales', $this->locales);
		$this->request->set('defaultLocale', $this->locales->getDefault());

		$this->toggleTriggers('DISABLE');

		try {
			return $this->rebuild($classes);
		} finally {
			$this->toggleTriggers('ENABLE');
		}
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
	 * @return array<string, string>
	 */
	private function dynamicMap(string $class, string $uid, array $content, int &$dynamic): array
	{
		$node = $this->factory->create($class, $this->context, $this->cms, [
			'uid' => $uid,
			'content' => $content,
		]);

		if (!$node instanceof TitleContract) {
			return [];
		}

		$dynamic++;
		$original = $this->request->get('locale', null);

		try {
			return $this->resolver->dynamicMap(
				function (Locale $locale) use ($node): string {
					$this->request->set('locale', $locale);

					return $node->title();
				},
				$this->locales,
			);
		} finally {
			$this->request->set('locale', $original);
		}
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

	private function toggleTriggers(string $action): void
	{
		foreach (self::TRIGGERS as $trigger) {
			$sql = $this->conn->applyPlaceholders(
				"ALTER TABLE /*:cms.prefix:*/nodes {$action} TRIGGER /*:cms.obj:*/{$trigger}",
				__FILE__,
			);

			$this->db->execute($sql)->run();
		}
	}
}
