<?php

declare(strict_types=1);

namespace Cosray\Node;

use Celema\Quma\Database;
use Cosray\Exception\RoutePathError;
use Cosray\Field\Field;
use Cosray\Locale;
use Cosray\Locales;
use JsonException;

final class RoutePathGenerator
{
	private const MAX_PARENT_DEPTH = 5;

	public function __construct(
		private readonly Database $db,
		private readonly Types $types,
	) {}

	/**
	 * @param class-string $nodeClass
	 * @param array<string, mixed> $data
	 * @return array<string, string>
	 */
	public function generate(
		string $nodeClass,
		array $data,
		Locales $locales,
		?int $parentId = null,
	): array {
		return $this->generateFromRoute(
			$this->types->get($nodeClass, 'route'),
			$data,
			$locales,
			$parentId,
		);
	}

	/**
	 * @param class-string $nodeClass
	 * @param array<string, mixed> $data
	 * @return array<string, string>
	 */
	public function preview(
		string $nodeClass,
		array $data,
		Locales $locales,
		?int $parentId = null,
	): array {
		return $this->generateFromRoute(
			$this->types->get($nodeClass, 'route'),
			$data,
			$locales,
			$parentId,
			strict: false,
		);
	}

	/**
	 * The node's own content-field names a route template references, so the
	 * editor can live-preview generated paths when exactly those inputs
	 * change. Excludes uid, handle and the parent family — none of which are
	 * editable content fields of this node.
	 *
	 * @return list<string>
	 */
	public function referencedFields(mixed $route): array
	{
		$templates = match (true) {
			is_array($route) => array_values($route),
			is_string($route) => [$route],
			default => [],
		};
		$fields = [];

		foreach ($templates as $template) {
			if (!is_string($template) || !preg_match_all('/\{([^{}]+)\}/', $template, $matches)) {
				continue;
			}

			foreach ($matches[1] as $inner) {
				$selector = trim(explode('|', $inner)[0] ?? '');

				if (
					$selector === ''
					|| $selector === 'uid'
					|| $selector === 'handle'
					|| $selector === 'parent'
					|| str_starts_with($selector, 'parent.')
					|| str_starts_with($selector, 'parent(')
					|| str_starts_with($selector, 'parent?')
				) {
					continue;
				}

				$fields[$selector] = true;
			}
		}

		return array_keys($fields);
	}

	/**
	 * @param array<string, string>|string|mixed $route
	 * @param array<string, mixed> $data
	 * @return array<string, string>
	 */
	public function generateFromRoute(
		mixed $route,
		array $data,
		Locales $locales,
		?int $parentId = null,
		bool $strict = true,
	): array {
		if (!is_string($route) && !is_array($route)) {
			return [];
		}

		$parents = [];
		$paths = [];

		foreach ($locales as $locale) {
			$template = $this->template($route, $locale);

			if ($template === '') {
				continue;
			}

			$paths[$locale->id] = $this->expand($template, $data, $locale, $parents, $parentId, $strict);
		}

		return $paths;
	}

	/**
	 * @param array<string, string>|string $route
	 */
	private function template(array|string $route, Locale $locale): string
	{
		if (is_string($route)) {
			return $route;
		}

		return $route[$locale->id] ?? '';
	}

	/**
	 * @param array<string, mixed> $data
	 * @param array<int, array{node: int, parent: ?int, uid: string, handle: ?string, content: array<string, mixed>, paths: array<string, string>}> $parents
	 */
	private function expand(
		string $template,
		array $data,
		Locale $locale,
		array &$parents,
		?int $parentId,
		bool $strict,
	): string {
		$usesParentPath = false;
		$path = preg_replace_callback(
			'/\{([^{}]+)\}/',
			function (array $matches) use (
				$data,
				$locale,
				&$parents,
				$parentId,
				$strict,
				&$usesParentPath,
			): string {
				try {
					return $this->resolve($matches[1], $data, $locale, $parents, $parentId, $usesParentPath);
				} catch (RoutePathError $e) {
					if ($strict) {
						throw $e;
					}

					return $this->friendlyPlaceholder($matches[1]);
				}
			},
			$template,
		);

		if (!is_string($path)) {
			throw new RoutePathError('Could not generate route path');
		}

		if ($strict && (str_contains($path, '{') || str_contains($path, '}'))) {
			throw new RoutePathError('Invalid route path placeholder syntax');
		}

		return $usesParentPath ? $this->normalizePath($path) : $path;
	}

	/**
	 * @param array<string, mixed> $data
	 * @param array<int, array{node: int, parent: ?int, uid: string, handle: ?string, content: array<string, mixed>, paths: array<string, string>}> $parents
	 */
	private function resolve(
		string $placeholder,
		array $data,
		Locale $locale,
		array &$parents,
		?int $parentId,
		bool &$usesParentPath,
	): string {
		[$selector, $transformers] = $this->parsePlaceholder($placeholder);

		if ($selector === 'uid') {
			return $this->requiredSlug($data['uid'] ?? null, $placeholder, $transformers);
		}

		if ($selector === 'handle') {
			return $this->requiredSlug($data['handle'] ?? null, $placeholder, $transformers);
		}

		$parentSelector = $this->parentSelector($selector);

		if ($parentSelector !== null) {
			if ($parentSelector['field'] === null && $transformers !== []) {
				throw new RoutePathError(
					'Route path transformers are not supported for parent path placeholders',
				);
			}

			if ($parentSelector['field'] === null) {
				$usesParentPath = true;

				if ($parentSelector['optional'] && !$this->hasParent($data, $parentId)) {
					return '';
				}
			}

			$parent = $this->ancestor($data, $parentId, $parents, $parentSelector['depth']);

			if ($parentSelector['field'] === null) {
				return trim($this->parentPath($parent, $locale, $placeholder), '/');
			}

			return $this->resolveParent(
				$parentSelector['field'],
				$parent,
				$locale,
				$placeholder,
				$transformers,
			);
		}

		$content = $data['content'] ?? [];

		return $this->field(
			is_array($content) ? $content : [],
			$selector,
			$locale,
			$placeholder,
			$transformers,
		);
	}

	/** @return array{0: string, 1: list<string>} */
	private function parsePlaceholder(string $placeholder): array
	{
		$parts = array_map(trim(...), explode('|', $placeholder));
		$selector = array_shift($parts) ?? '';

		if ($selector === '') {
			throw new RoutePathError('Invalid route path placeholder syntax');
		}

		foreach ($parts as $transformer) {
			if (!in_array(
				$transformer,
				['lowercase', 'uppercase', 'titlecase', 'keepcase', 'dashes', 'underscore'],
				true,
			)) {
				throw new RoutePathError(sprintf('Unknown route path transformer: %s', $transformer));
			}
		}

		return [$selector, $parts];
	}

	/** @return ?array{depth: int, field: ?string, optional: bool} */
	private function parentSelector(string $selector): ?array
	{
		if (
			$selector !== 'parent'
			&& !str_starts_with($selector, 'parent.')
			&& !str_starts_with($selector, 'parent(')
			&& !str_starts_with($selector, 'parent?')
		) {
			return null;
		}

		if ($selector === 'parent?') {
			return [
				'depth' => 1,
				'field' => null,
				'optional' => true,
			];
		}

		if (!preg_match('/^parent(?:\(([1-9]\d*)\))?(?:\.(.+))?$/', $selector, $matches)) {
			throw new RoutePathError('Invalid route path parent syntax');
		}

		$depth = isset($matches[1]) && $matches[1] !== '' ? (int) $matches[1] : 1;

		if ($depth > self::MAX_PARENT_DEPTH) {
			throw new RoutePathError(sprintf(
				'Route path parent depth cannot exceed %d',
				self::MAX_PARENT_DEPTH,
			));
		}

		$field = $matches[2] ?? null;

		return [
			'depth' => $depth,
			'field' => is_string($field) && $field !== '' ? $field : null,
			'optional' => false,
		];
	}

	private function friendlyPlaceholder(string $placeholder): string
	{
		$selector = trim(explode('|', $placeholder)[0] ?? '');
		$parent = $this->friendlyParentPlaceholder($selector);

		if ($parent !== null) {
			return $parent;
		}

		return '[' . $this->friendlyLabel($selector) . ']';
	}

	private function friendlyParentPlaceholder(string $selector): ?string
	{
		if ($selector === 'parent?') {
			return '[parent path]';
		}

		if (!preg_match('/^parent(?:\((\d+)\))?(?:\.(.+))?$/', $selector, $matches)) {
			return null;
		}

		$depth = isset($matches[1]) && $matches[1] !== '' ? (int) $matches[1] : 1;
		$prefix = $depth > 1 ? 'ancestor' : 'parent';
		$field = $matches[2] ?? null;

		if (!is_string($field) || $field === '') {
			return "[{$prefix} path]";
		}

		return "[{$prefix} {$this->friendlyLabel($field)}]";
	}

	private function friendlyLabel(string $selector): string
	{
		$label = str_replace(['.', '_', '-', '(', ')'], ' ', $selector);
		$label = preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1 $2', $label) ?? $label;
		$label = preg_replace('/([a-z0-9])([A-Z])/', '$1 $2', $label) ?? $label;
		$label = preg_replace('/\s+/', ' ', $label) ?? $label;
		$label = strtolower(trim($label));

		return $label === '' ? 'value' : $label;
	}

	/**
	 * @param array<string, mixed> $data
	 * @param array<int, array{node: int, parent: ?int, uid: string, handle: ?string, content: array<string, mixed>, paths: array<string, string>}> $parents
	 * @return array{node: int, parent: ?int, uid: string, handle: ?string, content: array<string, mixed>, paths: array<string, string>}
	 */
	private function ancestor(array $data, ?int $parentId, array &$parents, int $depth): array
	{
		$parents[1] ??= $this->parent($data, $parentId);

		for ($level = 2; $level <= $depth; $level++) {
			if (isset($parents[$level])) {
				continue;
			}

			$node = $parents[$level - 1]['parent'];

			if ($node === null) {
				throw new RoutePathError('Ancestor node not found for route path');
			}

			$parents[$level] = $this->parentByNode($node);
		}

		return $parents[$depth];
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array{node: int, parent: ?int, uid: string, handle: ?string, content: array<string, mixed>, paths: array<string, string>}
	 */
	private function parent(array $data, ?int $parentId): array
	{
		if ($parentId !== null) {
			return $this->parentByNode($parentId);
		}

		$parentUid = $data['parent'] ?? null;

		if (!is_string($parentUid) || trim($parentUid) === '') {
			throw new RoutePathError('A parent is required for this node route');
		}

		return $this->parentByUid(trim($parentUid));
	}

	/** @param array<string, mixed> $data */
	private function hasParent(array $data, ?int $parentId): bool
	{
		if ($parentId !== null) {
			return true;
		}

		$parentUid = $data['parent'] ?? null;

		return is_string($parentUid) && trim($parentUid) !== '';
	}

	/** @return array{node: int, parent: ?int, uid: string, handle: ?string, content: array<string, mixed>, paths: array<string, string>} */
	private function parentByNode(int $node): array
	{
		$parent = $this->db->nodes->routeParentByNode(['node' => $node])->first();

		if (!$parent) {
			throw new RoutePathError('Parent node not found for route path');
		}

		return $this->parentRow($parent);
	}

	/** @return array{node: int, parent: ?int, uid: string, handle: ?string, content: array<string, mixed>, paths: array<string, string>} */
	private function parentByUid(string $uid): array
	{
		$parent = $this->db->nodes->routeParentByUid(['uid' => $uid])->first();

		if (!$parent) {
			throw new RoutePathError('Parent node not found for route path');
		}

		return $this->parentRow($parent);
	}

	/**
	 * @param array<string, mixed> $parent
	 * @return array{node: int, parent: ?int, uid: string, handle: ?string, content: array<string, mixed>, paths: array<string, string>}
	 */
	private function parentRow(array $parent): array
	{
		$content = $this->decodeContent($parent['content'] ?? '{}');
		$handle = $parent['handle'] ?? null;
		$node = (int) $parent['node'];

		return [
			'node' => $node,
			'parent' => $this->nodeId($parent['parent'] ?? null),
			'uid' => (string) $parent['uid'],
			'handle' => is_string($handle) && $handle !== '' ? $handle : null,
			'content' => $content,
			'paths' => $this->parentPaths($node),
		];
	}

	private function nodeId(mixed $node): ?int
	{
		if (is_int($node)) {
			return $node;
		}

		if (is_string($node) && ctype_digit($node)) {
			return (int) $node;
		}

		return null;
	}

	/**
	 * @return array<string, string>
	 */
	private function parentPaths(int $node): array
	{
		$paths = [];

		foreach ($this->db->paths->activeByNode(['node' => $node])->all() as $path) {
			$locale = $path['locale'] ?? null;
			$value = $path['path'] ?? null;

			if (is_string($locale) && is_string($value) && trim($value) !== '') {
				$paths[$locale] = $value;
			}
		}

		return $paths;
	}

	/**
	 * @param array{uid: string, handle: ?string, content: array<string, mixed>, paths: array<string, string>} $parent
	 * @param list<string> $transformers
	 */
	private function resolveParent(
		string $placeholder,
		array $parent,
		Locale $locale,
		string $fullPlaceholder,
		array $transformers,
	): string {
		return match ($placeholder) {
			'uid' => $this->requiredSlug($parent['uid'], $fullPlaceholder, $transformers),
			'handle' => $this->requiredSlug($parent['handle'], $fullPlaceholder, $transformers),
			default => $this->field(
				$parent['content'],
				$placeholder,
				$locale,
				$fullPlaceholder,
				$transformers,
			),
		};
	}

	/** @param array{paths: array<string, string>} $parent */
	private function parentPath(array $parent, Locale $locale, string $placeholder): string
	{
		$current = $locale;

		while ($current !== null) {
			$path = $this->pathValue($parent['paths'][$current->id] ?? null);

			if ($path !== null) {
				return $path;
			}

			$current = $current->fallback();
		}

		throw new RoutePathError(sprintf('Could not resolve route placeholder: {%s}', $placeholder));
	}

	private function pathValue(mixed $path): ?string
	{
		if (!is_string($path)) {
			return null;
		}

		$path = trim($path);

		return $path === '' ? null : $path;
	}

	private function normalizePath(string $path): string
	{
		$path = preg_replace('#/+#', '/', $path) ?? '';

		if ($path === '') {
			return '/';
		}

		return str_starts_with($path, '/') ? $path : '/' . $path;
	}

	/**
	 * @param array<string, mixed> $content
	 * @param list<string> $transformers
	 */
	private function field(
		array $content,
		string $field,
		Locale $locale,
		string $placeholder,
		array $transformers,
	): string {
		$value = $content[$field]['value'] ?? null;

		if (!is_array($value)) {
			throw new RoutePathError(sprintf('Could not resolve route placeholder: {%s}', $placeholder));
		}

		$current = $locale;

		while ($current !== null) {
			$resolved = $this->slugValue($value[$current->id] ?? null, $transformers);

			if ($resolved !== null) {
				return $resolved;
			}

			$current = $current->fallback();
		}

		$resolved = $this->slugValue($value[Field::NEUTRAL_LOCALE] ?? null, $transformers);

		if ($resolved !== null) {
			return $resolved;
		}

		throw new RoutePathError(sprintf('Could not resolve route placeholder: {%s}', $placeholder));
	}

	/** @param list<string> $transformers */
	private function requiredSlug(mixed $value, string $placeholder, array $transformers): string
	{
		if (!is_string($value) || trim($value) === '') {
			throw new RoutePathError(sprintf('Could not resolve route placeholder: {%s}', $placeholder));
		}

		$slug = $this->slugify(
			$this->transformCase($value, $transformers),
			$this->separator($transformers),
		);

		if ($slug === '') {
			throw new RoutePathError(sprintf('Could not resolve route placeholder: {%s}', $placeholder));
		}

		return $slug;
	}

	/** @param list<string> $transformers */
	private function slugValue(mixed $value, array $transformers): ?string
	{
		if (!is_string($value) && !is_int($value) && !is_float($value)) {
			return null;
		}

		$slug = $this->slugify(
			$this->transformCase((string) $value, $transformers),
			$this->separator($transformers),
		);

		return $slug === '' ? null : $slug;
	}

	/** @param list<string> $transformers */
	private function transformCase(string $value, array $transformers): string
	{
		$case = 'lowercase';

		foreach ($transformers as $transformer) {
			if (!in_array($transformer, ['lowercase', 'uppercase', 'titlecase', 'keepcase'], true)) {
				continue;
			}

			$case = $transformer;
		}

		return match ($case) {
			'uppercase' => strtoupper($value),
			'titlecase' => ucwords(strtolower($value)),
			'keepcase' => $value,
			default => strtolower($value),
		};
	}

	/** @param list<string> $transformers */
	private function separator(array $transformers): string
	{
		$separator = '-';

		foreach ($transformers as $transformer) {
			$separator = match ($transformer) {
				'dashes' => '-',
				'underscore' => '_',
				default => $separator,
			};
		}

		return $separator;
	}

	private function slugify(string $value, string $separator): string
	{
		$value = trim(preg_replace('/\s+/', $separator, $value) ?? '');
		$value = substr($value, 0, 255);
		$value = preg_replace('/[^A-Za-z0-9_-]+/', '', $value) ?? '';

		$quoted = preg_quote($separator, '/');
		$value = preg_replace("/{$quoted}{$quoted}+/", $separator, $value) ?? '';

		return trim($value, $separator);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function decodeContent(mixed $content): array
	{
		if (is_array($content)) {
			return $content;
		}

		if (!is_string($content) || $content === '') {
			return [];
		}

		try {
			$decoded = json_decode($content, true, flags: JSON_THROW_ON_ERROR);
		} catch (JsonException $e) {
			throw new RoutePathError('Could not decode parent content for route path', previous: $e);
		}

		return is_array($decoded) ? $decoded : [];
	}
}
