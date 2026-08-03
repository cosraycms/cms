<?php

declare(strict_types=1);

namespace Cosray\Controller;

use Celema\Core\Exception\HttpBadRequest;
use Celema\Core\Exception\HttpNotFound;
use Celema\Core\Factory\Factory;
use Celema\Core\Response;
use Cosray\Cms;
use Cosray\Context;
use Cosray\Contract\HttpDelete;
use Cosray\Contract\HttpGet;
use Cosray\Contract\HttpPost;
use Cosray\Contract\HttpPut;
use Cosray\Exception\RuntimeException;
use Cosray\Middleware\Permission;
use Cosray\Node\Factory as NodeFactory;
use Cosray\Node\Serializer;
use Cosray\Node\Types;
use Cosray\Node\View;
use Cosray\Node\Wrapper;
use Cosray\Util\Path;

class Node
{
	/**
	 * Request method to the node interface that answers it, and the method
	 * to call. A node implementing one takes over that request completely.
	 *
	 * @var array<string, array{class-string, string}>
	 */
	private const array HANDLERS = [
		'GET' => [HttpGet::class, 'httpGet'],
		'POST' => [HttpPost::class, 'httpPost'],
		'PUT' => [HttpPut::class, 'httpPut'],
		'DELETE' => [HttpDelete::class, 'httpDelete'],
	];

	public function __construct(
		protected readonly Factory $factory,
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

		return $this->dispatch($page, $context, $cms);
	}

	#[Permission('panel')]
	public function preview(Context $context, Cms $cms, string $slug): Response
	{
		$page = $cms->node->byPath('/' . $slug);

		if (!$page) {
			throw new HttpNotFound($context->request);
		}

		// Preview goes through the same dispatch as the public path, so a
		// node that answers GET itself is previewed the way it is served.
		return $this->dispatch($page, $context, $cms);
	}

	private function dispatch(object $page, Context $context, Cms $cms): Response
	{
		$request = $context->request;
		$method = $request->method();
		$handler = self::HANDLERS[$method] ?? null;
		$node = Wrapper::unwrap($page);

		if ($handler !== null && $node instanceof $handler[0]) {
			return $node->{$handler[1]}();
		}

		if ($method !== 'GET') {
			throw new HttpBadRequest();
		}

		if ($request->get('isXhr', false)) {
			return $this->jsonRead($page, $context, $cms);
		}

		return new View($node, $cms, $context, $this->types)->render();
	}

	private function jsonRead(object $node, Context $context, Cms $cms): Response
	{
		$inner = Wrapper::unwrap($node);

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
