<?php

declare(strict_types=1);

namespace Celemas\Cms;

use Celemas\Cms\Exception\RuntimeException;
use Celemas\Cms\Finder\Menu;
use Celemas\Cms\Finder\Node;
use Celemas\Cms\Finder\Nodes;
use Celemas\Cms\Finder\Render;
use Celemas\Cms\Node\Factory;
use Celemas\Cms\Node\Types;

/**
 * @property-read Nodes $nodes
 * @property-read Node $node
 */
class Cms
{
	private readonly Factory $nodeFactory;

	public function __construct(
		private readonly Context $context,
		private readonly Types $types,
	) {
		$this->nodeFactory = new Factory($context->container, types: $this->types);
	}

	public function __get($key): Nodes|Node|Menu
	{
		return match ($key) {
			'nodes' => new Nodes($this->context, $this, $this->nodeFactory, $this->types),
			'node' => new Node($this->context, $this, $this->nodeFactory, $this->types),
			default => throw new RuntimeException('Property not supported'),
		};
	}

	public function nodes(
		string $query = '',
	): Nodes {
		return new Nodes($this->context, $this, $this->nodeFactory, $this->types)->filter($query);
	}

	public function node(
		string $query,
		array $types = [],
		int $limit = 0,
		string $order = '',
	): array {
		$finder = new Nodes($this->context, $this, $this->nodeFactory, $this->types)->filter($query);

		if ($types !== []) {
			$finder->types(...$types);
		}

		if ($order !== '') {
			$finder->order($order);
		}

		if ($limit > 0) {
			$finder->limit($limit);
		}

		return iterator_to_array($finder);
	}

	public function menu(string $menu): Menu
	{
		return new Menu($this->context, $menu);
	}

	public function render(
		string $uid,
		array $templateContext = [],
		?bool $deleted = false,
		?bool $published = true,
	): Render {
		return new Render(
			$this->context,
			$this,
			$this->nodeFactory,
			$this->types,
			$uid,
			$templateContext,
			$deleted,
			$published,
		);
	}

	public function nodeFactory(): Factory
	{
		return $this->nodeFactory;
	}
}
