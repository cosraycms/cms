<?php

declare(strict_types=1);

namespace Cosray\Finder;

use Cosray\Cms;
use Cosray\Context;
use Cosray\Exception\RuntimeException;
use Iterator;

class Menu implements Iterator
{
	/** The `order` values a `children` item may configure. */
	public const array CHILD_ORDERS = ['title', 'created', 'created desc', 'changed desc'];

	protected array $items;
	protected int $pointer = 0;

	/**
	 * `$expand` resolves dynamic `children` items into node entries at
	 * read time; the panel editor turns it off to show the items as
	 * stored. Expansion needs the `$cms` finder entry point — without
	 * one, `children` items render as nothing.
	 */
	public function __construct(
		protected readonly Context $context,
		string $menu,
		protected readonly ?Cms $cms = null,
		bool $expand = true,
	) {
		$this->items = $this->makeTree(
			$context->db->menus->get(['menu' => $menu])->all(),
		);

		// An existing menu without items iterates nothing and renders as
		// an empty string; only an unknown menu is an error.
		if (
			count($this->items) === 0
			&& !$context->db->menus->exists(['menu' => $menu])->first()
		) {
			throw new RuntimeException('Menu not found');
		}

		if ($expand) {
			$this->items = $this->expand($this->items);
		}
	}

	public function rewind(): void
	{
		reset($this->items);
	}

	public function current(): MenuItem
	{
		return new MenuItem($this->context, current($this->items));
	}

	public function key(): string
	{
		return key($this->items);
	}

	public function next(): void
	{
		next($this->items);
	}

	public function valid(): bool
	{
		return key($this->items) !== null;
	}

	public function html(string $class = '', string $tag = 'nav'): string
	{
		return $this->compileHtml($this, $class, $tag);
	}

	protected function compileHtml(
		Iterator $items,
		string $class = '',
		string $tag = 'nav',
	): string {
		$out = '';
		$level = 1;

		foreach ($items as $item) {
			$level = $item->level();
			$itemClass = $item->class();
			$image = $item->image() ?: '';

			if ($image) {
				$image = sprintf(
					'<div class="nav-image"><img src="%s" alt="Navigation Icon"/></div>',
					$this->escape($image),
				);
			}

			$submenu = $this->compileHtml($item->children(), tag: '');

			if ($submenu) {
				$submenu = sprintf('<div class="nav-submenu">%s</div>', $submenu);
			}

			$content = sprintf(
				'%s<div class="nav-label"><span>%s</span></div>%s',
				$image,
				$this->escape($item->title()),
				$submenu,
			);
			$href = $item->href();

			if ($href !== null) {
				$content = sprintf('<a%s>%s</a>', $this->anchorAttributes($item, $href), $content);
			}

			$out .= sprintf(
				'<li class="nav-level-%s%s%s">%s</li>',
				(string) $level,
				$item->hasChildren() ? ' nav-has-children' : '',
				$itemClass ? ' ' . $this->escape($itemClass) : '',
				$content,
			);
		}

		if ($out === '') {
			return '';
		}

		if ($tag) {
			return sprintf(
				'<%s%s><ul class="nav-level-%s">%s</ul></%s>',
				$tag,
				$class ? sprintf(' class="%s"', $this->escape($class)) : '',
				$level,
				$out,
				$tag,
			);
		}

		return sprintf(
			'<ul class="%snav-level-%s">%s</ul>',
			$class ? $this->escape($class) . ' ' : '',
			$level,
			$out,
		);
	}

	protected function anchorAttributes(MenuItem $item, string $href): string
	{
		$attributes = sprintf(' href="%s"', $this->escape($href));
		$target = $item->target();

		if ($target !== null) {
			$attributes .= sprintf(' target="%s"', $this->escape($target));

			if ($target === '_blank') {
				$attributes .= ' rel="noopener"';
			}
		}

		return $attributes;
	}

	protected function escape(string $value): string
	{
		return htmlspecialchars($value, ENT_QUOTES);
	}

	/**
	 * Nests the sorted rows by their parent column. Splitting the CTE's
	 * dotted path would duplicate items whose ids contain a dot.
	 */
	protected function makeTree(array $items): array
	{
		$grouped = [];

		foreach ($items as $item) {
			$grouped[$item['parent'] ?? ''][$item['item']] = $item;
		}

		return $this->branch($grouped, '');
	}

	private function branch(array $grouped, string $parent): array
	{
		$tree = [];

		foreach ($grouped[$parent] ?? [] as $id => $item) {
			$item['children'] = $this->branch($grouped, $id);
			$tree[$id] = $item;
		}

		return $tree;
	}

	/**
	 * Replaces every `children` item in place with the linked node's
	 * published, visible children, `levels` deep and in the configured
	 * order. The entries are synthesized as `node` rows carrying their
	 * resolved title and path for the current locale, so rendering
	 * treats them exactly like hand-placed node items.
	 */
	protected function expand(array $items): array
	{
		$result = [];

		foreach ($items as $id => $item) {
			$data = json_decode((string) $item['data'], true);
			$data = is_array($data) ? $data : [];

			if (($data['type'] ?? '') === 'children') {
				$result += $this->childRows($data, (int) $item['level']);

				continue;
			}

			$item['children'] = $this->expand($item['children']);
			$result[$id] = $item;
		}

		return $result;
	}

	/** @return array<string, array> */
	private function childRows(array $data, int $level): array
	{
		$uid = $data['node'] ?? null;

		if ($this->cms === null || !is_string($uid) || $uid === '') {
			return [];
		}

		$order = $data['order'] ?? '';
		$order = in_array($order, self::CHILD_ORDERS, true) ? $order : 'title';

		return $this->nodeRows($uid, $level, max(1, (int) ($data['levels'] ?? 1)), $order);
	}

	/** @return array<string, array> */
	private function nodeRows(string $uid, int $level, int $depth, string $order): array
	{
		assert($this->cms !== null, 'childRows guards the finder entry point');
		$locale = $this->context->locale()->id;
		$rows = [];

		// The uid tie-break keeps equal sort keys deterministic.
		foreach ($this->cms->nodes()->childrenOf($uid)->order($order, 'id') as $node) {
			$childUid = (string) $node->meta->uid;
			$key = 'children:' . $childUid;

			$rows[$key] = [
				'item' => $key,
				'parent' => null,
				'level' => $level,
				'data' => json_encode([
					'type' => 'node',
					'node' => $childUid,
					'title' => [$locale => $node->title()],
					'path' => [$locale => $node->path()],
				]),
				'children' => $depth > 1
					? $this->nodeRows($childUid, $level + 1, $depth - 1, $order)
					: [],
			];
		}

		return $rows;
	}
}
