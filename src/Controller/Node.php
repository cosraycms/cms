<?php

declare(strict_types=1);

namespace Cosray\Controller;

use Celema\Core\Exception\HttpBadRequest;
use Celema\Core\Exception\HttpNotFound;
use Celema\Core\Factory\Factory;
use Celema\Core\Response;
use Cosray\Api\Responses;
use Cosray\Api\Serializer;
use Cosray\Cms;
use Cosray\Context;
use Cosray\Contract\HttpDelete;
use Cosray\Contract\HttpGet;
use Cosray\Contract\HttpPost;
use Cosray\Contract\HttpPut;
use Cosray\Contract\Json;
use Cosray\Exception\RuntimeException;
use Cosray\Middleware\Permission;
use Cosray\Node\Types;
use Cosray\Node\View;
use Cosray\Node\Wrapper;
use Cosray\Util\Path;

class Node
{
	public function __construct(
		protected readonly Factory $factory,
		protected readonly Types $types,
	) {}

	public function catchall(Context $context, Cms $cms): Response
	{
		$request = $context->httpRequest();
		$config = $context->config;
		$path = $request->uri()->getPath();
		$prefix = $config->app->urlPrefix;

		if ($prefix) {
			$path = preg_replace('/^' . preg_quote($prefix, '/') . '/', '', $path) ?? $path;
		}

		$node = $cms->node->byPath($path === '' ? '/' : $path);

		if (!$node) {
			try {
				$path = Path::inside($config->path->public, $path, checkIsFile: true);

				return Response::create($this->factory)->file($path);
			} catch (RuntimeException $e) {
				return (
					$this->redirectToActivePath($context, $path)
						?? throw new HttpNotFound($request, previous: $e)
				);
			}
		}

		return $this->dispatch($node, $context, $cms);
	}

	/** The working copy of a node, published or not, rendered as the public path would. */
	#[Permission('panel')]
	public function preview(Context $context, Cms $cms, string $uid): Response
	{
		$node = $cms->node->working($uid);

		if (!$node || !(bool) $this->types->get(Wrapper::unwrap($node)::class, 'renderable', false)) {
			throw new HttpNotFound($context->httpRequest());
		}

		// Preview goes through the same dispatch as the public path, so a
		// node that answers GET itself is previewed the way it is served.
		return $this->dispatch($node, $context, $cms);
	}

	private function dispatch(object $node, Context $context, Cms $cms): Response
	{
		$request = $context->httpRequest();
		$method = $request->method();
		$inner = Wrapper::unwrap($node);
		// A node implementing the request method's interface takes over that request completely.
		$response = match (true) {
			$method === 'GET' && $inner instanceof HttpGet => $inner->httpGet(),
			$method === 'POST' && $inner instanceof HttpPost => $inner->httpPost(),
			$method === 'PUT' && $inner instanceof HttpPut => $inner->httpPut(),
			$method === 'DELETE' && $inner instanceof HttpDelete => $inner->httpDelete(),
			default => null,
		};

		if ($response !== null) {
			return $response;
		}

		if ($method !== 'GET') {
			throw new HttpBadRequest();
		}

		if ($request->get('isXhr', false)) {
			return $this->jsonRead($node, $context);
		}

		return new View($inner, $cms, $context, $this->types)->render();
	}

	private function jsonRead(object $node, Context $context): Response
	{
		$inner = Wrapper::unwrap($node);
		$serializer = new Serializer($context, $this->types);
		$data = $inner instanceof Json ? $inner->json($serializer) : $serializer->node($node);
		$content = Responses::encode($data);

		return new Response(
			$this->factory
				->response()
				->withHeader('Content-Type', 'application/json'),
		)->body($content);
	}

	/**
	 * A permanent redirect from a path the node no longer uses to its active
	 * path in the current locale or the nearest fallback locale.
	 */
	protected function redirectToActivePath(Context $context, string $path): ?Response
	{
		$db = $context->db;
		$path = $db->paths->byPath(['path' => $path])->first();

		if ($path && !($path['inactive'] === null)) {
			$paths = $db->paths->activeByNode(['node' => $path['node']])->all();

			$pathsByLocale = array_combine(
				array_map(static fn($p) => $p['locale'], $paths),
				array_map(static fn($p) => $p['path'], $paths),
			);

			$query = $context->httpRequest()->uri()->getQuery();
			$locale = $context->locale();

			while ($locale) {
				$path = $pathsByLocale[$locale->id] ?? null;

				if ($path) {
					$location = $context->config->app->urlPrefix . $path;

					return Response::create($this->factory)->redirect(
						$query === '' ? $location : $location . '?' . $query,
						301,
					);
				}

				$locale = $locale->fallback();
			}
		}

		return null;
	}
}
