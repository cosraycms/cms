<?php

declare(strict_types=1);

namespace Cosray\Finder;

use Cosray\Context;
use Cosray\Exception\RuntimeException;
use Iterator;

class Menu implements Iterator
{
	protected array $items;
	protected int $pointer = 0;

	public function __construct(
		protected readonly Context $context,
		string $menu,
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
}
