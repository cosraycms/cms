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
use Cosray\Exception\ParserException;
use Cosray\Security\NodeAccess;
use Cosray\User;
use JsonException;
use Psr\Http\Message\ResponseInterface as Response;

final class Nodes
{
	public function __construct(
		private readonly Responses $responses,
		private readonly NodeAccess $access,
		private readonly Serializer $serializer,
	) {}

	public function list(Request $request, Cms $cms): Response
	{
		return $this->listing($request, $cms, NodeQuery::fromQuery($request->params()));
	}

	/** The listing with its parameters in a JSON body, for uid lists and filters too long for a URL. */
	public function query(Request $request, Cms $cms): Response
	{
		try {
			$body = $request->json(JSON_OBJECT_AS_ARRAY | JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			throw new HttpBadRequest($request, payload: ['message' => 'The body is not valid JSON.']);
		}

		if (!is_array($body) || $body !== [] && array_is_list($body)) {
			throw new HttpBadRequest($request, payload: ['message' => 'The body is a JSON object.']);
		}

		return $this->listing($request, $cms, NodeQuery::fromBody($body));
	}

	public function get(Request $request, Cms $cms, string $uid): Response
	{
		$params = $request->params();
		$deleted = NodeQuery::state($params['deleted'] ?? null, 'deleted', false);
		$node = $cms->node->get(array_filter(
			[
				'uid' => $uid,
				'published' => null,
				'deleted' => $deleted,
			],
			static fn(mixed $value): bool => $value !== null,
		));

		// A node the client may not read does not exist for it.
		if ($node === null || !$this->access->reads(self::user($request), $node)) {
			throw new HttpNotFound($request, payload: ['message' => "There is no node '{$uid}'."]);
		}

		return $this->responses->json($this->serializer->node(
			$node,
			NodeQuery::source($params['view'] ?? null),
			NodeQuery::fields($params['fields'] ?? null),
		));
	}

	private function listing(Request $request, Cms $cms, NodeQuery $query): Response
	{
		$registered = $this->access->registered();
		$unknown = array_diff($query->types, array_keys($registered));

		if ($unknown !== []) {
			throw new HttpBadRequest($request, payload: [
				'message' => 'Unknown type: ' . implode(', ', $unknown) . '.',
			]);
		}

		$readable = $this->access->readable(self::user($request));
		$nodes = $cms
			->nodes()
			->published($query->published)
			->hidden($query->hidden)
			->deleted($query->deleted)
			->readable($readable['live'], $readable['any']);

		if ($query->types !== []) {
			$nodes->types(...$query->types);
		}

		if ($query->uids !== []) {
			$nodes->only(...$query->uids);
		}

		if ($query->handles !== []) {
			$nodes->match(['handle' => ['in' => $query->handles]]);
		}

		if ($query->parent !== null) {
			$nodes->childrenOf($query->parent);
		}

		try {
			if ($query->filter !== null) {
				$nodes->match($query->filter, NodeQuery::BUILTINS);
			}

			$total = $nodes->count();
			$nodes->order(...$query->order)->limit($query->limit)->offset($query->offset);
			$items = [];

			foreach ($nodes as $node) {
				$items[] = $this->serializer->node($node, $query->source, $query->fields);
			}
		} catch (ParserException $e) {
			throw new HttpBadRequest($request, payload: ['message' => $e->getMessage()], previous: $e);
		}

		return $this->responses->json([
			'items' => $items,
			'total' => $total,
			'limit' => $query->limit,
			'offset' => $query->offset,
		]);
	}

	private static function user(Request $request): ?User
	{
		$user = $request->get('user', null);

		return $user instanceof User ? $user : null;
	}
}
