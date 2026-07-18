<?php

declare(strict_types=1);

namespace Cosray\Controller;

use Celema\Container\Container;
use Celema\Core\Exception\HttpBadRequest;
use Celema\Core\Exception\HttpNotFound;
use Celema\Core\Factory\Factory;
use Celema\Core\Response;
use Cosray\Cms;
use Cosray\Context;
use Cosray\Exception\RuntimeException;
use Cosray\Middleware\Permission;
use Cosray\Node\Contract\HandlesFormPost;
use Cosray\Node\Factory as NodeFactory;
use Cosray\Node\Node;
use Cosray\Node\Serializer;
use Cosray\Node\Types;
use Cosray\Node\ViewRenderer;
use Cosray\Util\Path;
use ReflectionMethod;

class Page
{
	public function __construct(
		protected readonly Factory $factory,
		protected readonly Container $container,
		protected readonly Types $types,
	) {}

	public function catchall(Context $context, Cms $cms): Response
	{
		$request = $context->request;
		$config = $context->config;
		$path = $request->uri()->getPath();
		$prefix = $config->path->prefix;

		if ($prefix) {
			$path = preg_replace('/^' . preg_quote($prefix, '/') . '/', '', $path);
		}

		$page = $cms->node->byPath($path === '' ? '/' : $path);

		if (!$page) {
			try {
				$path = Path::inside($config->path->public, $path);

				return Response::create($this->factory)->file($path);
			} catch (RuntimeException $e) {
				$this->redirectIfExists($context, $path);

				throw new HttpNotFound($request, previous: $e);
			}
		}

		if ($request->get('isXhr', false)) {
			if ($request->method() === 'GET') {
				return $this->jsonRead($page, $context, $cms);
			}

			throw new HttpBadRequest();
		}

		return $this->dispatch($page, $context, $cms, $request->method(), $request->form());
	}

	#[Permission('panel')]
	public function preview(Context $context, Cms $cms, string $slug): Response
	{
		$page = $cms->node->byPath('/' . $slug);

		return $this->renderPage($page, $context, $cms);
	}

	private function dispatch(
		object $page,
		Context $context,
		Cms $cms,
		string $method,
		?array $formBody,
	): Response {
		return match ($method) {
			'GET' => $this->renderPage($page, $context, $cms),
			'POST' => $this->handleFormPost($page, $formBody),
			default => throw new HttpBadRequest(),
		};
	}

	private function renderPage(object $page, Context $context, Cms $cms): Response
	{
		$node = Node::unwrap($page);

		if (is_callable([$node, 'render'])) {
			return $node->render();
		}

		$renderer = new ViewRenderer($this->container, $this->factory, $this->types);

		return $renderer->renderPage(
			$node,
			NodeFactory::fieldNamesFor($node),
			$cms,
			$context->request,
			$context->config,
		);
	}

	private function jsonRead(object $node, Context $context, Cms $cms): Response
	{
		$inner = Node::unwrap($node);

		if (method_exists($inner, 'read')) {
			$data = $inner->read();
		} else {
			$nodeFactory = $cms->nodeFactory();
			$serializer = new Serializer(
				$this->types,
				$nodeFactory->uid(),
				$context->assets(),
				$context->paths(),
			);
			$data = $serializer->read(
				$inner,
				NodeFactory::dataFor($inner),
				NodeFactory::fieldNamesFor($inner),
			);
		}

		$content = json_encode(
			$data,
			JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
		);

		return new Response(
			$this->factory
				->response()
				->withHeader('Content-Type', 'application/json'),
		)->body($content);
	}

	private function handleFormPost(object $node, ?array $formBody): Response
	{
		$inner = Node::unwrap($node);

		if ($inner instanceof HandlesFormPost) {
			return $inner->formPost($formBody);
		}

		if (method_exists($inner, 'formPost')) {
			$method = new ReflectionMethod($inner, 'formPost');

			return $method->invoke($inner, $formBody);
		}

		throw new HttpBadRequest();
	}

	protected function redirectIfExists(Context $context, string $path): void
	{
		$db = $context->db;
		$path = $db->paths->byPath(['path' => $path])->first();

		if ($path && !($path['inactive'] === null)) {
			$paths = $db->paths->activeByNode(['node' => $path['node']])->all();

			$pathsByLocale = array_combine(
				array_map(static fn($p) => $p['locale'], $paths),
				array_map(static fn($p) => $p['path'], $paths),
			);

			$locale = $context->request->get('locale');

			while ($locale) {
				$path = $pathsByLocale[$locale->id] ?? null;

				if ($path) {
					header('Location: ' . $path, true, 301);
					exit();
				}

				$locale = $locale->fallback();
			}
		}
	}
}
