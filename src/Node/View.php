<?php

declare(strict_types=1);

namespace Cosray\Node;

use Celema\Core\Response;
use Cosray\Cms;
use Cosray\Context;
use Cosray\Contract\ViewContext;
use Cosray\Renderer;

/**
 * A node's own view, bound to the node it renders.
 *
 * Nodes inject it to render themselves — typically from an `Http*` handler
 * that answers with the node plus a message or the submitted values:
 *
 * ```php
 * public function httpPost(): Response
 * {
 *     return $this->view->render(['error' => __('Please confirm.')]);
 * }
 * ```
 *
 * The passed context wins over `ViewContext::viewContext()`, so a node can
 * declare defaults there and override them per request here.
 */
final class View
{
	public function __construct(
		private readonly object $node,
		private readonly Cms $cms,
		private readonly Context $context,
		private readonly Types $types,
	) {}

	/** @param array<string, mixed> $context */
	public function render(array $context = []): Response
	{
		return new Response(
			$this->context
				->factory
				->response()
				->withHeader('Content-Type', 'text/html; charset=utf-8'),
		)->body($this->output($context));
	}

	/**
	 * The rendered template as a string, for nodes that build their own
	 * response and for nodes rendered into another node's template.
	 *
	 * @param array<string, mixed> $context
	 */
	public function output(array $context = []): string
	{
		return $this->context
			->container
			->tag(Renderer::class)
			->get('view')
			->render(
				$this->types->schemaOf($this->node::class)->renderer,
				$this->templateContext($context),
			);
	}

	/**
	 * @param  array<string, mixed> $extra
	 * @return array<string, mixed>
	 */
	private function templateContext(array $extra): array
	{
		$request = $this->context->httpRequest();
		$proxy = new Wrapper(
			$this->node,
			Factory::fieldNamesFor($this->node),
			$this->types,
			$this->context,
			$this->cms,
			$this->cms->nodeFactory(),
		);

		return array_merge(
			[
				'node' => $proxy,
				'cms' => $this->cms,
				'locale' => $this->context->locale(),
				'locales' => $this->context->locales(),
				'request' => $request,
				'container' => $this->context->container,
				'debug' => $this->context->config->debug(),
				'env' => $this->context->config->env(),
			],
			$this->node instanceof ViewContext ? $this->node->viewContext($proxy) : [],
			$extra,
		);
	}
}
