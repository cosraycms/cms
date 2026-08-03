<?php

declare(strict_types=1);

namespace Cosray\Node;

use Celema\Container\Container;
use Celema\Core\Factory\Factory;
use Celema\Core\Response;
use Cosray\Cms;
use Cosray\Context;
use Cosray\Contract\ViewContext;
use Cosray\Renderer;

class ViewRenderer
{
	public function __construct(
		private readonly Container $container,
		private readonly Factory $factory,
		private readonly Types $types,
	) {}

	/**
	 * Render a page node to an HTML response.
	 *
	 * The node is wrapped in a Wrapper and passed to the template as
	 * '$page'. If the node implements ViewContext, its extra context is
	 * merged in.
	 */
	public function renderPage(
		object $node,
		array $fieldNames,
		Cms $cms,
		Context $context,
		array $extra = [],
	): Response {
		$request = $context->request;
		$proxy = $this->proxy($node, $fieldNames, $cms, $context);

		$baseContext = [
			'page' => $proxy,
			'cms' => $cms,
			'locale' => $request->get('locale'),
			'locales' => $request->get('locales'),
			'request' => $request,
			'container' => $this->container,
			'debug' => $context->config->debug(),
			'env' => $context->config->env(),
		];

		$baseContext = array_merge(
			$baseContext,
			$this->viewContext($node, $proxy),
			$extra,
		);

		return $this->doRender($node, $baseContext);
	}

	/**
	 * Render a node to an HTML string.
	 *
	 * The node is wrapped in a Wrapper and passed to the template as
	 * '$node'. If the node implements ViewContext, its extra context is
	 * merged in — an embedded node prepares its view the same way a page
	 * does.
	 */
	public function renderNode(
		object $node,
		array $fieldNames,
		Cms $cms,
		Context $context,
		array $extra = [],
	): string {
		$request = $context->request;
		$proxy = $this->proxy($node, $fieldNames, $cms, $context);

		$baseContext = array_merge(
			[
				'node' => $proxy,
				'cms' => $cms,
				'locale' => $request->get('locale'),
				'locales' => $request->get('locales'),
				'request' => $request,
				'container' => $this->container,
				'debug' => $context->config->debug(),
				'env' => $context->config->env(),
			],
			$this->viewContext($node, $proxy),
			$extra,
		);

		[$type, $id] = $this->resolveRenderer($node);
		$renderer = $this->container->tag(Renderer::class)->get($type);

		return $renderer->render($id, $baseContext);
	}

	/**
	 * Resolve the renderer type and template ID for a node.
	 *
	 * @return array{0: string, 1: string} [rendererType, templateId]
	 */
	public function resolveRenderer(object $node): array
	{
		return ['view', $this->types->schemaOf($node::class)->renderer];
	}

	/**
	 * A fully equipped proxy: built through the node factory with the cms
	 * and context, so `children()` works on the object the template gets.
	 */
	private function proxy(object $node, array $fieldNames, Cms $cms, Context $context): Wrapper
	{
		return new Wrapper(
			$node,
			$fieldNames,
			$this->types,
			$context->request,
			$context,
			$cms,
			$cms->nodeFactory(),
		);
	}

	/** @return array<string, mixed> */
	private function viewContext(object $node, Wrapper $proxy): array
	{
		return $node instanceof ViewContext ? $node->viewContext($proxy) : [];
	}

	private function doRender(object $node, array $context): Response
	{
		[$type, $id] = $this->resolveRenderer($node);
		$renderer = $this->container->tag(Renderer::class)->get($type);

		return new Response(
			$this->factory
				->response()
				->withHeader('Content-Type', 'text/html; charset=utf-8'),
		)->body(
			$renderer->render($id, $context),
		);
	}
}
