<?php

declare(strict_types=1);

namespace Cosray\Api\Controller;

use Celema\Core\Exception\HttpBadRequest;
use Celema\Core\Exception\HttpNotFound;
use Celema\Core\Request;
use Cosray\Api\NodeQuery;
use Cosray\Api\Responses;
use Cosray\Api\Serializer;
use Cosray\Cms;
use Cosray\Context;
use Cosray\Field\Services;
use Cosray\Security\NodeAccess;
use Cosray\User;
use Psr\Http\Message\ResponseInterface as Response;

/**
 * Resolves a URL path the way the website does, so a headless frontend can
 * route its own URLs: any published node with that active path, read in the
 * path's locale, or the path to redirect to when the node moved.
 */
final class Route
{
	public function __construct(
		private readonly Responses $responses,
	) {}

	public function route(Request $request, Context $context, Services $services, NodeAccess $access): Response
	{
		// Finder, values and serializer share this context, so switching
		// its locale switches the locale the node is read in.
		$cms = new Cms($context, $services);
		$serializer = new Serializer($context, $services->types);
		$params = $request->params();
		$path = $params['path'] ?? null;

		if (!is_string($path) || !str_starts_with($path, '/')) {
			throw new HttpBadRequest($request, payload: ['message' => '`path` is a URL path starting with a slash.']);
		}

		$row = $context->db->paths->byPath(['path' => $path])->first();
		$node = $row !== null && $row['inactive'] === null ? $cms->node->byPath($path) : null;
		$user = $request->get('user', null);

		if ($node !== null && !$access->serves($user instanceof User ? $user : null, $node)) {
			throw new HttpNotFound($request, payload: ['message' => "Nothing answers at '{$path}'."]);
		}

		if ($node === null) {
			$redirect = $row !== null ? $this->activePath($context, (int) $row['node']) : null;

			if ($redirect === null || $redirect === $path) {
				throw new HttpNotFound($request, payload: ['message' => "Nothing answers at '{$path}'."]);
			}

			return $this->responses->json(['redirect' => $redirect]);
		}

		$locale = isset($params['locale']) ? $context->locale() : $context->locales()->get((string) $row['locale']);
		$data = $context->withLocale($locale, static fn(): array => $serializer->node(
			$node,
			NodeQuery::source($params['view'] ?? null),
			NodeQuery::fields($params['fields'] ?? null),
		));

		return $this->responses->json(['node' => $data], headers: ['ETag' => Serializer::etag($node)]);
	}

	/** The node's active path in the request locale or its nearest fallback. */
	private function activePath(Context $context, int $node): ?string
	{
		$paths = [];

		foreach ($context->db->paths->activeByNode(['node' => $node])->all() as $active) {
			$paths[(string) $active['locale']] = (string) $active['path'];
		}

		$locale = $context->locale();

		while ($locale) {
			if (isset($paths[$locale->id])) {
				return $paths[$locale->id];
			}

			$locale = $locale->fallback();
		}

		return $paths === [] ? null : reset($paths);
	}
}
