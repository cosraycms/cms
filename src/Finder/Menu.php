<?php

declare(strict_types=1);

namespace Celemas\Cms\Finder;

use Celemas\Cms\Context;
use Celemas\Cms\Exception\RuntimeException;
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

		if (count($this->items) === 0) {
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

		foreach ($items as $item) {
			$class = $item->class();
			$image = $item->image() ?: '';

			if ($image) {
				$image = sprintf('<div class="nav-image"><img src="%s" alt="Navigation Icon"/></div>', $image);
			}

			$submenu = $this->compileHtml($item->children(), tag: '');

			if ($submenu) {
				$submenu = sprintf('<div class="nav-submenu">%s</div>', $submenu);
			}

			$content = sprintf(
				'%s<div class="nav-label"><span>%s</span></div>%s',
				$image,
				$item->title(),
				$submenu,
			);

			$content = match ($item->type()) {
				'page' => sprintf('<a href="%s">%s</a>', $item->path(), $content),
				default => $content,
			};

			$out .= sprintf(
				'<li class="nav-level-%s%s%s">%s</li>',
				(string) $item->level(),
				$item->hasChildren() ? ' nav-has-children' : '',
				$class ? ' ' . $class : '',
				$content,
			);
		}

		if ($out) {
			$class = $class ? sprintf(' class="%s"', $class) : '';

			return $tag
				? sprintf(
					'<%s%s><ul class="nav-level-%s">%s</ul></%s>',
					$tag,
					$class,
					$item->level(),
					$out,
					$tag,
				)
				: sprintf('<ul%s class="nav-level-%s">%s</ul>', $class, $item->level(), $out);
		}

		return '';
	}

	protected function makeTree(array $items): array
	{
		$tree = [];

		foreach ($items as $item) {
			$arr = &$tree;

			foreach (explode('.', $item['path']) as $segment) {
				if (isset($arr[$segment])) {
					$arr = &$arr[$segment]['children'];
				} else {
					$arr[$segment] = $item;
					$arr[$segment]['children'] = [];
				}
			}
		}

		return $tree;
	}
}
