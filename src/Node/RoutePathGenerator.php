<?php

declare(strict_types=1);

namespace Cosray\Node;

use Celemas\Quma\Database;
use Cosray\Exception\RoutePathError;
use Cosray\Field\Field;
use Cosray\Locale;
use Cosray\Locales;
use JsonException;

final class RoutePathGenerator
{
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

		$parent = null;
		$paths = [];

		foreach ($locales as $locale) {
			$template = $this->template($route, $locale);

			if ($template === '') {
				continue;
			}

			$paths[$locale->id] = $this->expand($template, $data, $locale, $parent, $parentId, $strict);
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
	 * @param ?array{uid: string, handle: ?string, content: array<string, mixed>, paths: array<string, string>} $parent
	 */
	private function expand(
		string $template,
		array $data,
		Locale $locale,
		?array &$parent,
		?int $parentId,
		bool $strict,
	): string {
		$usesParentPath = false;
		$path = preg_replace_callback(
			'/\{([^{}]+)\}/',
			function (array $matches) use (
				$data,
				$locale,
				&$parent,
				$parentId,
				$strict,
				&$usesParentPath,
			): string {
				try {
					return $this->resolve($matches[1], $data, $locale, $parent, $parentId, $usesParentPath);
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
			throw new RoutePathError(_('Could not generate route path'));
		}

		if ($strict && (str_contains($path, '{') || str_contains($path, '}'))) {
			throw new RoutePathError(_('Invalid route path placeholder syntax'));
		}

		return $usesParentPath ? $this->normalizePath($path) : $path;
	}

	/**
	 * @param array<string, mixed> $data
	 * @param ?array{uid: string, handle: ?string, content: array<string, mixed>, paths: array<string, string>} $parent
	 */
	private function resolve(
		string $placeholder,
		array $data,
		Locale $locale,
		?array &$parent,
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

		if ($selector === 'parent') {
			$usesParentPath = true;

			if ($transformers !== []) {
				throw new RoutePathError(_('Route path transformers are not supported for {parent}'));
			}

			$parent ??= $this->parent($data, $parentId);

			return trim($this->parentPath($parent, $locale, $placeholder), '/');
		}

		if (str_starts_with($selector, 'parent.')) {
			$parent ??= $this->parent($data, $parentId);
			$parentPlaceholder = substr($selector, strlen('parent.'));

			return $this->resolveParent($parentPlaceholder, $parent, $locale, $placeholder, $transformers);
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
			throw new RoutePathError(_('Invalid route path placeholder syntax'));
		}

		foreach ($parts as $transformer) {
			if (!in_array(
				$transformer,
				['lowercase', 'uppercase', 'titlecase', 'dashes', 'underscore'],
				true,
			)) {
				throw new RoutePathError(sprintf(_('Unknown route path transformer: %s'), $transformer));
			}
		}

		return [$selector, $parts];
	}

	private function friendlyPlaceholder(string $placeholder): string
	{
		$selector = trim(explode('|', $placeholder)[0] ?? '');

		if ($selector === 'parent') {
			return '[parent path]';
		}

		$label = str_replace(['.', '_', '-'], ' ', $selector);
		$label = preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1 $2', $label) ?? $label;
		$label = preg_replace('/([a-z0-9])([A-Z])/', '$1 $2', $label) ?? $label;
		$label = preg_replace('/\s+/', ' ', $label) ?? $label;
		$label = strtolower(trim($label));

		return '[' . ($label === '' ? 'value' : $label) . ']';
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array{uid: string, handle: ?string, content: array<string, mixed>, paths: array<string, string>}
	 */
	private function parent(array $data, ?int $parentId): array
	{
		if ($parentId !== null) {
			$parent = $this->db->nodes->routeParentByNode(['node' => $parentId])->first();
		} else {
			$parentUid = $data['parent'] ?? null;

			if (!is_string($parentUid) || trim($parentUid) === '') {
				throw new RoutePathError(_('A parent is required for this node route'));
			}

			$parent = $this->db->nodes->routeParentByUid(['uid' => trim($parentUid)])->first();
		}

		if (!$parent) {
			throw new RoutePathError(_('Parent node not found for route path'));
		}

		$content = $this->decodeContent($parent['content'] ?? '{}');
		$handle = $parent['handle'] ?? null;

		return [
			'uid' => (string) $parent['uid'],
			'handle' => is_string($handle) && $handle !== '' ? $handle : null,
			'content' => $content,
			'paths' => $this->parentPaths((int) $parent['node']),
		];
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

		throw new RoutePathError(sprintf(_('Could not resolve route placeholder: {%s}'), $placeholder));
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
			throw new RoutePathError(sprintf(_('Could not resolve route placeholder: {%s}'), $placeholder));
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

		throw new RoutePathError(sprintf(_('Could not resolve route placeholder: {%s}'), $placeholder));
	}

	/** @param list<string> $transformers */
	private function requiredSlug(mixed $value, string $placeholder, array $transformers): string
	{
		if (!is_string($value) || trim($value) === '') {
			throw new RoutePathError(sprintf(_('Could not resolve route placeholder: {%s}'), $placeholder));
		}

		$slug = $this->slugify(
			$this->transformCase($value, $transformers),
			$this->separator($transformers),
		);

		if ($slug === '') {
			throw new RoutePathError(sprintf(_('Could not resolve route placeholder: {%s}'), $placeholder));
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
			if (!in_array($transformer, ['lowercase', 'uppercase', 'titlecase'], true)) {
				continue;
			}

			$case = $transformer;
		}

		return match ($case) {
			'uppercase' => strtoupper($value),
			'titlecase' => ucwords(strtolower($value)),
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
			throw new RoutePathError(_('Could not decode parent content for route path'), previous: $e);
		}

		return is_array($decoded) ? $decoded : [];
	}
}
