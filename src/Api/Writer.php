<?php

declare(strict_types=1);

namespace Cosray\Api;

use Celema\Core\Exception\HttpBadRequest;
use Celema\Core\Exception\HttpConflict;
use Celema\Sire\Issue;
use Cosray\Actor;
use Cosray\Cms;
use Cosray\Context;
use Cosray\Node\Factory;
use Cosray\Node\PathManager;
use Cosray\Node\ReservedPaths;
use Cosray\Node\Store;
use Cosray\Node\Types;
use Cosray\Node\Wrapper;
use Cosray\Util\Transaction;

/**
 * Turns API request bodies into node writes. A body uses the source view:
 * `content` holds stored values without the field class, which the writer
 * adds; the derived members of the representation are ignored, so a node
 * read in the source view can be written back as it is.
 */
final class Writer
{
	private const array DERIVED = ['locale', 'title', 'path', 'created', 'changed', 'deleted', 'fields'];
	private const array MEMBERS = ['type', 'uid', 'handle', 'parent', 'published', 'hidden', 'paths', 'content'];

	private readonly Store $store;

	public function __construct(
		private readonly Context $context,
		private readonly Cms $cms,
		private readonly Types $types,
	) {
		$this->store = new Store(
			$context->db,
			new PathManager(ReservedPaths::fromConfig($context->config)),
			$types,
			$cms->nodeFactory()->uid(),
			factory: $cms->nodeFactory(),
			cms: $cms,
			context: $context,
		);
	}

	/**
	 * Members the body leaves out start empty, content fields at their defaults.
	 *
	 * @param class-string $class
	 * @param array<array-key, mixed> $body
	 * @return string the new node's uid
	 */
	public function create(string $class, array $body, Actor $actor, ?string $uid = null): string
	{
		self::assertMembers($body);
		$node = $this->cms->nodeFactory()->blueprint($class, $this->context, $this->cms);
		$data = [
			'published' => self::bool($body, 'published', false),
			'hidden' => self::bool($body, 'hidden', false),
			'locked' => false,
			'handle' => self::nullableString($body, 'handle', null),
			'parent' => self::nullableString($body, 'parent', null),
			'paths' => self::paths($body, []),
			'content' => $this->content($node, $this->withDefaults($node, self::contentOf($body))),
		];

		if ($uid !== null) {
			$data['uid'] = $uid;
		}

		return $this->store->create($node, $data, $this->context->locales(), $actor)['uid'];
	}

	/**
	 * Replaces the content; fields the body leaves out return to their
	 * defaults, while node members it leaves out keep their values.
	 *
	 * @param array<array-key, mixed> $body
	 */
	public function replace(object $node, array $body, Actor $actor): void
	{
		self::assertMembers($body);
		$inner = Wrapper::unwrap($node);

		if ($this->store->holdsDrafts($inner) && Factory::meta($inner, 'draft') !== null) {
			throw new HttpConflict(payload: [
				'message' => 'The node has unpublished changes from an editor; PATCH it, or have them published or discarded first.',
			]);
		}

		$data = $this->data($inner, $body);
		$data['content'] = $this->content($inner, $this->withDefaults($inner, self::contentOf($body)));
		$this->store->save($inner, $data, $this->context->locales(), $actor);
	}

	/**
	 * Applies a JSON Merge Patch to the live node and to an editor's working
	 * copy alike, so publishing that copy later does not undo the patch.
	 * Unpublishing folds the patched working copy into the node, as the
	 * panel does.
	 *
	 * @param array<array-key, mixed> $patch
	 */
	public function patch(object $node, array $patch, Actor $actor): void
	{
		self::assertMembers($patch);
		$inner = Wrapper::unwrap($node);
		$uid = (string) Factory::meta($inner, 'uid');
		$working = Factory::meta($inner, 'draft') !== null ? $this->cms->node->working($uid) : null;
		$locales = $this->context->locales();

		Transaction::run($this->context->db, function () use ($inner, $patch, $actor, $working, $uid, $locales): void {
			$live = $this->patched($inner, $patch);

			if ($working === null) {
				$this->store->save($inner, $live, $locales, $actor);

				return;
			}

			$draft = Wrapper::unwrap($working);
			$this->store->draft($draft, $this->patched($draft, $patch), $locales, $actor);

			if ($live['published']) {
				$this->store->save($inner, $live, $locales, $actor);

				return;
			}

			$fresh = $this->cms->node->get(['uid' => $uid, 'published' => null]);

			if ($fresh !== null) {
				$this->store->unpublish(Wrapper::unwrap($fresh), $locales, $actor);
			}
		});
	}

	public function delete(object $node, Actor $actor, bool $children): void
	{
		$this->store->delete(Wrapper::unwrap($node), $actor, $children);
	}

	/**
	 * @param array<array-key, mixed> $patch
	 * @return array<string, mixed>
	 */
	private function patched(object $node, array $patch): array
	{
		$current = Factory::dataFor($node);
		$stored = is_array($current['content'] ?? null) ? $current['content'] : [];
		$data = $this->data($node, $patch);

		if (array_key_exists('content', $patch)) {
			if (!is_array($patch['content']) || $patch['content'] !== [] && array_is_list($patch['content'])) {
				throw self::invalid(['content'], 'object', '`content` is an object of fields.');
			}

			$this->assertFields($node, $patch['content']);
			$merged = MergePatch::apply($stored, $patch['content']);
			$stored = is_array($merged) ? $merged : [];
		}

		$data['content'] = $this->content($node, $stored);

		return $data;
	}

	/**
	 * The node members for a write over an existing node: what the body
	 * names, the node's current values otherwise.
	 *
	 * @param array<array-key, mixed> $body
	 * @return array<string, mixed>
	 */
	private function data(object $node, array $body): array
	{
		$current = Factory::meta(...);
		$type = $body['type'] ?? null;
		$uid = $body['uid'] ?? null;

		if ($uid !== null && $uid !== $current($node, 'uid')) {
			throw self::invalid(['uid'], 'immutable', 'The uid of a node does not change.');
		}

		if ($type !== null && $type !== $this->types->get($node::class, 'handle')) {
			throw new HttpConflict(payload: ['message' => 'The node has another type.']);
		}

		return [
			'uid' => (string) $current($node, 'uid'),
			'handle' => self::nullableString($body, 'handle', $current($node, 'handle')),
			'parent' => self::nullableString($body, 'parent', $current($node, 'parent')),
			'published' => self::bool($body, 'published', (bool) $current($node, 'published')),
			'hidden' => self::bool($body, 'hidden', (bool) $current($node, 'hidden')),
			'locked' => (bool) $current($node, 'locked'),
			'paths' => self::paths($body, is_array($current($node, 'paths')) ? $current($node, 'paths') : []),
		];
	}

	/**
	 * Adds every field's class, as stored content carries it.
	 *
	 * @param array<array-key, mixed> $values
	 * @return array<string, mixed>
	 */
	private function content(object $node, array $values): array
	{
		$this->assertFields($node, $values);
		$content = [];

		foreach ($values as $name => $value) {
			if (!is_array($value) || $value !== [] && array_is_list($value)) {
				throw self::invalid(['content', $name], 'object', "`{$name}` is an object like {\"value\": …}.");
			}

			$content[$name] = ['type' => Factory::fieldFor($node, (string) $name)::class, ...$value];
		}

		return $content;
	}

	/**
	 * @param array<array-key, mixed> $values
	 * @return array<array-key, mixed>
	 */
	private function withDefaults(object $node, array $values): array
	{
		$this->assertFields($node, $values);
		$blueprint = $this->cms->nodeFactory()->blueprint($node::class, $this->context, $this->cms);
		$content = [];

		foreach (Factory::fieldNamesFor($blueprint) as $name) {
			$default = Factory::fieldFor($blueprint, $name)->structure();
			unset($default['type']);
			$content[$name] = $values[$name] ?? $default;
		}

		return $content;
	}

	/** @param array<array-key, mixed> $values */
	private function assertFields(object $node, array $values): void
	{
		$unknown = array_diff(array_map(strval(...), array_keys($values)), Factory::fieldNamesFor($node));

		if ($unknown !== []) {
			throw new HttpBadRequest(payload: [
				'message' => 'The node type has no field ' . implode(', ', $unknown) . '.',
				'errors' => array_values(array_map(
					static fn(string $name): Issue => new Issue(
						['content', $name],
						'unknown',
						'The type has no such field.',
					),
					$unknown,
				)),
			]);
		}
	}

	/** @param array<array-key, mixed> $body */
	private static function assertMembers(array $body): void
	{
		$unknown = array_diff(array_map(strval(...), array_keys($body)), [...self::MEMBERS, ...self::DERIVED]);

		if ($unknown !== []) {
			throw new HttpBadRequest(payload: ['message' => 'Unknown member: ' . implode(', ', $unknown) . '.']);
		}
	}

	/**
	 * @param array<array-key, mixed> $body
	 * @return array<array-key, mixed>
	 */
	private static function contentOf(array $body): array
	{
		$content = $body['content'] ?? [];

		if (!is_array($content) || $content !== [] && array_is_list($content)) {
			throw self::invalid(['content'], 'object', '`content` is an object of fields.');
		}

		return $content;
	}

	/**
	 * Null in a body or patch keeps the current value.
	 *
	 * @param array<array-key, mixed> $body
	 */
	private static function bool(array $body, string $member, bool $default): bool
	{
		$value = $body[$member] ?? $default;

		return is_bool($value) ? $value : throw self::invalid([$member], 'bool', "`{$member}` is true or false.");
	}

	/** @param array<array-key, mixed> $body */
	private static function nullableString(array $body, string $member, mixed $default): ?string
	{
		$value = array_key_exists($member, $body) ? $body[$member] : $default;

		return (
			$value === null || is_string($value)
				? $value
				: throw self::invalid([$member], 'string', "`{$member}` is a string or null.")
		);
	}

	/**
	 * Paths the body names replace those locales' paths; null removes one.
	 *
	 * @param array<array-key, mixed> $body
	 * @param array<array-key, mixed> $current
	 * @return array<string, string>
	 */
	private static function paths(array $body, array $current): array
	{
		$given = $body['paths'] ?? [];

		if (!is_array($given) || $given !== [] && array_is_list($given)) {
			throw self::invalid(['paths'], 'object', '`paths` maps locales to paths.');
		}

		$merged = MergePatch::apply($current, $given);
		$paths = [];

		foreach (is_array($merged) ? $merged : [] as $locale => $path) {
			if (!is_string($path)) {
				throw self::invalid(['paths', (string) $locale], 'string', 'A path is a string.');
			}

			$paths[(string) $locale] = $path;
		}

		return $paths;
	}

	/** @param list<string|int> $path */
	private static function invalid(array $path, string $code, string $message): HttpBadRequest
	{
		return new HttpBadRequest(payload: ['message' => $message, 'errors' => [new Issue($path, $code, $message)]]);
	}
}
