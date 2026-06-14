<?php

declare(strict_types=1);

namespace Cosray\Finder;

use Celemas\Core\Exception\HttpBadRequest;
use Cosray\Cms;
use Cosray\Context;
use Cosray\Exception\RuntimeException;
use Cosray\Node\Factory;
use Cosray\Node\Types;
use Cosray\Node\ViewRenderer;
use Cosray\Plugin;
use Throwable;

class Render
{
	protected object $node;

	public function __construct(
		private readonly Context $context,
		private readonly Cms $cms,
		private readonly Factory $nodeFactory,
		private readonly Types $types,
		string $uid,
		private readonly array $templateContext = [],
		?bool $deleted = false,
		?bool $published = true,
	) {
		$data = $this->context
			->db
			->nodes
			->find([
				'uid' => $uid,
				'published' => $published,
				'deleted' => $deleted,
			])
			->one();
		$class = $this->context
			->container
			->tag(Plugin::NODE_TAG)
			->entry($data['type_handle'])
			->definition();

		if (!(bool) $this->types->get($class, 'renderable', false)) {
			throw new RuntimeException('Invalid renderable node class ' . $class);
		}

		$data['content'] = json_decode($data['content'], true);
		$this->node = $this->nodeFactory->create($class, $context, $cms, $data);
	}

	public function __toString(): string
	{
		try {
			$renderer = new ViewRenderer(
				$this->context->container,
				$this->context->factory,
				$this->nodeFactory->hydrator(),
				$this->types,
			);

			return $renderer->renderNode(
				$this->node,
				Factory::fieldNamesFor($this->node),
				$this->cms,
				$this->context->request,
				$this->context->config,
				$this->templateContext,
			);
		} catch (Throwable $e) {
			if ($this->context->config->debug()) {
				throw $e;
			}

			throw new HttpBadRequest($this->context->request, previous: $e);
		}
	}
}
