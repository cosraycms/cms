<?php

declare(strict_types=1);

namespace Cosray\Api\Controller;

use Celema\Core\Exception\HttpBadRequest;
use Celema\Core\Exception\HttpConflict;
use Celema\Core\Exception\HttpForbidden;
use Celema\Core\Exception\HttpNotFound;
use Celema\Core\Exception\HttpUnauthorized;
use Celema\Core\Request;
use Closure;
use Cosray\Actor;
use Cosray\Api\Exception\PreconditionFailed;
use Cosray\Api\Exception\UnsupportedMediaType;
use Cosray\Api\NodeQuery;
use Cosray\Api\PreviewToken;
use Cosray\Api\Responses;
use Cosray\Api\Serializer;
use Cosray\Api\SyncCursor;
use Cosray\Api\Writer;
use Cosray\Cms;
use Cosray\Config;
use Cosray\Exception\ParserException;
use Cosray\Finder\Nodes as NodeFinder;
use Cosray\Finder\Order;
use Cosray\Node\Factory;
use Cosray\Node\Wrapper;
use Cosray\Security\NodeAccess;
use Cosray\User;
use JsonException;
use PDOException;
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

	/**
	 * Full-text search over what the website lists: `q` takes the words,
	 * every listing parameter narrows the result, and relevance orders it
	 * unless `order` names another order.
	 */
	public function search(Request $request, Cms $cms): Response
	{
		$params = $request->params();
		$q = $params['q'] ?? null;

		if (!is_string($q) || trim($q) === '') {
			throw new HttpBadRequest($request, payload: ['message' => '`q` names the words to search for.']);
		}

		unset($params['q']);

		return $this->listing($request, $cms, NodeQuery::fromQuery($params), trim($q));
	}

	/** The listing with its parameters in a JSON body, for uid lists and filters too long for a URL. */
	public function query(Request $request, Cms $cms): Response
	{
		return $this->listing($request, $cms, NodeQuery::fromBody($this->body($request, ['application/json'])));
	}

	/**
	 * `preview` takes a token the panel issued for this node and answers
	 * with its working copy, published or not.
	 */
	public function get(Request $request, Cms $cms, Config $config, string $uid): Response
	{
		$params = $request->params();
		$preview = $params['preview'] ?? null;

		if ($preview !== null) {
			$tokens = PreviewToken::fromConfig($config);

			if ($tokens === null || !is_string($preview) || $tokens->uid($preview) !== $uid) {
				throw new HttpUnauthorized($request, payload: [
					'message' => 'The preview token is invalid or expired.',
				]);
			}

			$node = $cms->node->working($uid)
				?? throw new HttpNotFound($request, payload: ['message' => "There is no node '{$uid}'."]);
		} else {
			$deleted = NodeQuery::state($params['deleted'] ?? null, 'deleted', false);
			$node = $this->readable($request, $cms, $uid, $deleted);
		}

		return $this->responses->json($this->serializer->node(
			$node,
			NodeQuery::source($params['view'] ?? null),
			NodeQuery::fields($params['fields'] ?? null),
		), headers: ['ETag' => Serializer::etag($node)]);
	}

	/** Creates a node; the body names its type and may choose its uid. */
	public function create(Request $request, Cms $cms, Writer $writer): Response
	{
		$body = $this->body($request, ['application/json']);
		$class = $this->type($request, $body['type'] ?? null);
		$uid = $body['uid'] ?? null;

		if ($uid !== null && (!is_string($uid) || preg_match('/^[A-Za-z0-9-_.]{1,64}$/', $uid) !== 1)) {
			throw new HttpBadRequest($request, payload: [
				'message' => '`uid` is up to 64 letters, digits, dashes, underscores and dots.',
			]);
		}

		$this->allow($request, NodeAccess::CREATE, $class);
		$uid = $writer->create($class, $body, $this->actor($request), $uid);

		return $this->written($request, $cms, $uid, 201);
	}

	/**
	 * Creates the node under this uid or replaces its content. `If-Match`
	 * guards a replacement, `If-None-Match: *` makes the request create only.
	 */
	public function put(Request $request, Cms $cms, Writer $writer, string $uid): Response
	{
		$body = $this->body($request, ['application/json']);

		if (array_key_exists('uid', $body) && $body['uid'] !== $uid) {
			throw new HttpBadRequest($request, payload: [
				'message' => 'The uid in the body differs from the uid in the URL.',
			]);
		}

		$status = $writer->exclusive($uid, function () use ($request, $cms, $writer, $uid, $body): int {
			$node = $cms->node->get(['uid' => $uid, 'published' => null]);
			$this->preconditions($request, $node);

			if ($node === null) {
				$class = $this->type($request, $body['type'] ?? null);
				$this->allow($request, NodeAccess::CREATE, $class);
				$writer->create($class, $body, $this->actor($request), $uid);

				return 201;
			}

			$this->assertWritable($request, $node);
			$writer->replace($node, $body, $this->actor($request));

			return 200;
		});

		return $this->written($request, $cms, $uid, $status);
	}

	/** Applies a JSON Merge Patch to the node and to an editor's working copy of it. */
	public function patch(Request $request, Cms $cms, Writer $writer, string $uid): Response
	{
		$patch = $this->body($request, ['application/merge-patch+json', 'application/json']);
		$writer->exclusive($uid, function () use ($request, $cms, $writer, $uid, $patch): void {
			$node = $this->readable($request, $cms, $uid, false);
			$this->preconditions($request, $node);
			$this->assertWritable($request, $node);
			$writer->patch($node, $patch, $this->actor($request));
		});

		return $this->written($request, $cms, $uid, 200);
	}

	/** Soft-deletes the node; `?children=true` takes its descendants along. */
	public function delete(Request $request, Cms $cms, Writer $writer, string $uid): Response
	{
		$children = NodeQuery::state($request->params()['children'] ?? null, 'children', false) === true;
		$writer->exclusive($uid, function () use ($request, $cms, $writer, $uid, $children): void {
			$node = $this->readable($request, $cms, $uid, false);
			$this->preconditions($request, $node);
			$this->allow($request, NodeAccess::DELETE, Wrapper::unwrap($node)::class);
			$writer->delete($node, $this->actor($request), $children);
		});

		return $this->responses->empty();
	}

	private function listing(Request $request, Cms $cms, NodeQuery $query, ?string $fulltext = null): Response
	{
		$unknown = array_diff($query->types, array_keys($this->access->registered()));

		if ($unknown !== []) {
			throw new HttpBadRequest($request, payload: [
				'message' => 'Unknown type: ' . implode(', ', $unknown) . '.',
			]);
		}

		if ($query->sync !== null) {
			if ($fulltext !== null) {
				throw new HttpBadRequest($request, payload: [
					'message' => 'A search does not sync; list the nodes with `changedSince` instead.',
				]);
			}

			return $this->sync($request, $cms, $query, $query->sync);
		}

		$readable = $this->access->readable(self::user($request));
		$nodes = $cms
			->nodes()
			->published($query->published)
			->hidden($query->hidden)
			->deleted($query->deleted)
			->readable($readable['live'], $readable['any']);

		if ($fulltext !== null) {
			$nodes->fulltext($fulltext);
		}

		[$items, $total] = $this->guarded($request, function () use ($nodes, $query, $fulltext): array {
			$this->narrow($nodes, $query);
			$total = $nodes->count();

			if ($fulltext === null || $query->ordered) {
				$nodes->order(...$query->order);
			}

			$nodes->limit($query->limit)->offset($query->offset);
			$items = [];

			foreach ($nodes as $node) {
				$item = $this->serializer->node($node, $query->source, $query->fields);
				$search = $node->meta->search;

				if ($search !== null) {
					$item['search'] = ['score' => $search->score, 'snippet' => (string) $search->snippet->html()];
				}

				$items[] = $item;
			}

			return [$items, $total];
		});

		return $this->responses->json([
			'items' => $items,
			'total' => $total,
			'limit' => $query->limit,
			'offset' => $query->offset,
		]);
	}

	/**
	 * The next changes after the cursor, in change order: nodes that pass the
	 * state filters come as items, the others, which the client should drop,
	 * under `removed` with only their uid, type and change time. `cursor`
	 * goes on after the last change given, `more` tells whether others
	 * follow right away.
	 */
	private function sync(Request $request, Cms $cms, NodeQuery $query, SyncCursor $cursor): Response
	{
		$user = self::user($request);
		$readable = $this->access->readable($user);
		$nodes = $cms
			->nodes()
			->published(null)
			->hidden(null)
			->deleted(null)
			->readable([], [...$readable['live'], ...$readable['any']]);

		[$items, $removed, $last, $more] = $this->guarded($request, function () use (
			$nodes,
			$query,
			$cursor,
			$user,
		): array {
			$this->narrow($nodes, $query);
			$nodes
				->match($cursor->filter())
				->order(new Order('changed'), new Order('uid'))
				->limit($query->limit + 1);
			$items = [];
			$removed = [];
			$last = null;

			foreach ($nodes as $node) {
				if ((count($items) + count($removed)) === $query->limit) {
					return [$items, $removed, $last, true];
				}

				$last = Wrapper::unwrap($node);

				if (self::passes($last, $query) && $this->access->reads($user, $node)) {
					$items[] = $this->serializer->node($node, $query->source, $query->fields);

					continue;
				}

				$item = $this->serializer->node($node, fields: []);
				$removed[] = ['uid' => $item['uid'], 'type' => $item['type'], 'changed' => $item['changed']];
			}

			return [$items, $removed, $last, false];
		});
		$next = $last === null
			? $cursor
			: SyncCursor::after((string) Factory::meta($last, 'changed'), (string) Factory::meta($last, 'uid'));

		return $this->responses->json([
			'items' => $items,
			'removed' => $removed,
			'limit' => $query->limit,
			'cursor' => $next->token(),
			'more' => $more,
		]);
	}

	/** The query's conditions besides the states, which listing and sync treat apart. */
	private function narrow(NodeFinder $nodes, NodeQuery $query): void
	{
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

		if ($query->filter !== null) {
			$nodes->match($query->filter, NodeQuery::BUILTINS);
		}
	}

	/**
	 * Runs the query, answering a filter or order the stored values do not
	 * fit with 400.
	 *
	 * @template T
	 *
	 * @param Closure(): T $query
	 * @return T
	 */
	private function guarded(Request $request, Closure $query): mixed
	{
		try {
			return $query();
		} catch (ParserException $e) {
			throw new HttpBadRequest($request, payload: ['message' => $e->getMessage()], previous: $e);
		} catch (PDOException $e) {
			// Class 22, data exceptions: a typed order or filter met values of another type.
			if (!str_starts_with((string) $e->getCode(), '22')) {
				throw $e;
			}

			throw new HttpBadRequest(
				$request,
				payload: [
					'message' => 'The filter or order does not fit the stored values.',
				],
				previous: $e,
			);
		}
	}

	/** Whether the node is in the states the query asks for. */
	private static function passes(object $node, NodeQuery $query): bool
	{
		$states = [
			[$query->published, (bool) Factory::meta($node, 'published')],
			[$query->hidden, (bool) Factory::meta($node, 'hidden')],
			[$query->deleted, Factory::meta($node, 'deleted') !== null],
		];

		foreach ($states as [$wanted, $actual]) {
			if ($wanted !== null && $wanted !== $actual) {
				return false;
			}
		}

		return true;
	}

	/** A node the client may read; any other answers 404, as if it did not exist. */
	private function readable(Request $request, Cms $cms, string $uid, ?bool $deleted): object
	{
		$node = $cms->node->get(array_filter(
			[
				'uid' => $uid,
				'published' => null,
				'deleted' => $deleted,
			],
			static fn(mixed $value): bool => $value !== null,
		));

		if ($node === null || !$this->access->reads(self::user($request), $node)) {
			throw new HttpNotFound($request, payload: ['message' => "There is no node '{$uid}'."]);
		}

		return $node;
	}

	private function assertWritable(Request $request, object $node): void
	{
		$inner = Wrapper::unwrap($node);

		if (!$this->access->reads(self::user($request), $node)) {
			throw new HttpNotFound($request, payload: ['message' => 'There is no such node.']);
		}

		if (Factory::meta($inner, 'deleted') !== null) {
			throw new HttpConflict($request, payload: ['message' => 'The node is deleted.']);
		}

		$this->allow($request, NodeAccess::CHANGE, $inner::class);

		if ((bool) Factory::meta($inner, 'locked')) {
			throw new HttpConflict($request, payload: ['message' => 'The node is locked.']);
		}
	}

	/** @param class-string $class */
	private function allow(Request $request, string $action, string $class): void
	{
		if (!$this->access->allows(self::user($request), $action, $class)) {
			throw new HttpForbidden($request, payload: [
				'message' => "The token may not {$action} nodes of this type.",
			]);
		}
	}

	/** @return class-string */
	private function type(Request $request, mixed $type): string
	{
		$registered = $this->access->registered();

		if (!is_string($type) || !isset($registered[$type])) {
			throw new HttpBadRequest($request, payload: ['message' => 'Name a known node `type`.']);
		}

		return $registered[$type];
	}

	private function preconditions(Request $request, ?object $node): void
	{
		$psr = $request->unwrap();
		$ifMatch = trim($psr->getHeaderLine('If-Match'));
		$ifNoneMatch = trim($psr->getHeaderLine('If-None-Match'));

		if ($ifNoneMatch === '*' && $node !== null) {
			throw new PreconditionFailed($request, payload: ['message' => 'The node exists.']);
		}

		if ($ifMatch === '') {
			return;
		}

		if ($node === null) {
			throw new PreconditionFailed($request, payload: ['message' => 'The node does not exist.']);
		}

		$tags = array_map(trim(...), explode(',', $ifMatch));

		if ($ifMatch !== '*' && !in_array(Serializer::etag($node), $tags, true)) {
			throw new PreconditionFailed($request, payload: ['message' => 'The node changed since it was read.']);
		}
	}

	/**
	 * @param list<string> $types
	 * @return array<array-key, mixed>
	 */
	private function body(Request $request, array $types): array
	{
		$type = strtolower(trim(explode(';', $request->unwrap()->getHeaderLine('Content-Type'))[0]));

		if (!in_array($type, $types, true)) {
			throw new UnsupportedMediaType($request, payload: ['message' => 'Send ' . implode(' or ', $types) . '.']);
		}

		try {
			$body = $request->json(JSON_OBJECT_AS_ARRAY | JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			throw new HttpBadRequest($request, payload: ['message' => 'The body is not valid JSON.']);
		}

		if (!is_array($body) || $body !== [] && array_is_list($body)) {
			throw new HttpBadRequest($request, payload: ['message' => 'The body is a JSON object.']);
		}

		return $body;
	}

	/** The node after a write, in the view the request asks for. */
	private function written(Request $request, Cms $cms, string $uid, int $status): Response
	{
		$node = $cms->node->get(['uid' => $uid, 'published' => null])
			?? throw new HttpNotFound($request, payload: ['message' => "There is no node '{$uid}'."]);
		$params = $request->params();
		$headers = ['ETag' => Serializer::etag($node)];

		if ($status === 201) {
			$headers['Location'] =
				rtrim($request->uri()->getPath(), '/') . ($request->method() === 'POST' ? "/{$uid}" : '');
		}

		return $this->responses->json(
			$this->serializer->node(
				$node,
				NodeQuery::source($params['view'] ?? null),
				NodeQuery::fields($params['fields'] ?? null),
			),
			$status,
			$headers,
		);
	}

	private function actor(Request $request): Actor
	{
		$user = self::user($request);

		return $user === null ? Actor::system() : new Actor($user->id);
	}

	private static function user(Request $request): ?User
	{
		$user = $request->get('user', null);

		return $user instanceof User ? $user : null;
	}
}
