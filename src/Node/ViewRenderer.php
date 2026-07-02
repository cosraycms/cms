<?php

declare(strict_types=1);

namespace Cosray\Node;

use Celemas\Container\Container;
use Celemas\Core\Factory\Factory;
use Celemas\Core\Request;
use Celemas\Core\Response;
use Cosray\Cms;
use Cosray\Config;
use Cosray\Node\Contract\ProvidesRenderContext;
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
	 * The node is wrapped in a Node and passed to the template as
	 * '$page'. If the node implements ProvidesRenderContext, its
	 * extra context is merged in.
	 */
	public function renderPage(
		object $node,
		array $fieldNames,
		Cms $cms,
		Request $request,
		Config $config,
		array $context = [],
	): Response {
		$proxy = new Node($node, $fieldNames, $this->types, $request);

		$baseContext = [
			'page' => $proxy,
			'cms' => $cms,
			'locale' => $request->get('locale'),
			'locales' => $request->get('locales'),
			'request' => $request,
			'container' => $this->container,
			'debug' => $config->debug(),
			'env' => $config->env(),
		];

		if ($node instanceof ProvidesRenderContext) {
			$baseContext = array_merge($baseContext, $node->renderContext());
		}

		$baseContext = array_merge($baseContext, $context);

		return $this->doRender($node, $baseContext);
	}

	/**
	 * Render a node to an HTML string.
	 *
	 * The node is wrapped in a Node and passed to the template as
	 * '$node'.
	 */
	public function renderNode(
		object $node,
		array $fieldNames,
		Cms $cms,
		Request $request,
		Config $config,
		array $context = [],
	): string {
		$proxy = new Node($node, $fieldNames, $this->types, $request);

		$baseContext = array_merge([
			'node' => $proxy,
			'cms' => $cms,
			'locale' => $request->get('locale'),
			'locales' => $request->get('locales'),
			'request' => $request,
			'container' => $this->container,
			'debug' => $config->debug(),
			'env' => $config->env(),
		], $context);

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
