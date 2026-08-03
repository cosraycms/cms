<?php

declare(strict_types=1);

namespace Cosray\Node;

use Celema\Core\Response;
use Cosray\Cms;
use Cosray\Context;

/**
 * A node's own view, bound to the node the factory built it for.
 *
 * Nodes inject it to render themselves — typically from an `Http*` handler
 * that answers with the page plus a message or the submitted values:
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
		private readonly ViewRenderer $renderer,
		private readonly Cms $cms,
		private readonly Context $context,
	) {}

	/** @param array<string, mixed> $context */
	public function render(array $context = []): Response
	{
		return $this->renderer->renderPage(
			$this->node,
			Factory::fieldNamesFor($this->node),
			$this->cms,
			$this->context,
			$context,
		);
	}

	/**
	 * The rendered page as a string, for nodes that build their own response.
	 *
	 * @param array<string, mixed> $context
	 */
	public function html(array $context = []): string
	{
		return (string) $this->render($context)->getBody();
	}
}
