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
	 * @param ?array{uid: string, handle: ?string, content: array<string, mixed>} $parent
	 */
	private function expand(
		string $template,
		array $data,
		Locale $locale,
		?array &$parent,
		?int $parentId,
		bool $strict,
	): string {
		$path = preg_replace_callback(
			'/\{([^{}]+)\}/',
			function (array $matches) use ($data, $locale, &$parent, $parentId, $strict): string {
				try {
					return $this->resolve($matches[1], $data, $locale, $parent, $parentId);
				} catch (RoutePathError $e) {
					if ($strict) {
						throw $e;
					}

					return '{' . $matches[1] . '}';
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

		return $path;
	}

	/**
	 * @param array<string, mixed> $data
	 * @param ?array{uid: string, handle: ?string, content: array<string, mixed>} $parent
	 */
	private function resolve(
		string $placeholder,
		array $data,
		Locale $locale,
		?array &$parent,
		?int $parentId,
	): string {
		if ($placeholder === 'uid') {
			return $this->requiredString($data['uid'] ?? null, $placeholder);
		}

		if ($placeholder === 'handle') {
			return $this->requiredString($data['handle'] ?? null, $placeholder);
		}

		if (str_starts_with($placeholder, 'parent.')) {
			$parent ??= $this->parent($data, $parentId);
			$parentPlaceholder = substr($placeholder, strlen('parent.'));

			return $this->resolveParent($parentPlaceholder, $parent, $locale, $placeholder);
		}

		$content = $data['content'] ?? [];

		return $this->field(is_array($content) ? $content : [], $placeholder, $locale, $placeholder);
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array{uid: string, handle: ?string, content: array<string, mixed>}
	 */
	private function parent(array $data, ?int $parentId): array
	{
		if ($parentId !== null) {
			$parent = $this->db
				->execute(
					'SELECT n.uid, h.handle, n.content
					FROM cms.nodes n
					LEFT JOIN cms.node_handles h ON h.node = n.node
					WHERE n.node = :node AND n.deleted IS NULL
					LIMIT 1',
					['node' => $parentId],
				)
				->first();
		} else {
			$parentUid = $data['parent'] ?? null;

			if (!is_string($parentUid) || trim($parentUid) === '') {
				throw new RoutePathError(_('A parent is required for this node route'));
			}

			$parent = $this->db
				->execute(
					'SELECT n.uid, h.handle, n.content
					FROM cms.nodes n
					LEFT JOIN cms.node_handles h ON h.node = n.node
					WHERE n.uid = :uid AND n.deleted IS NULL
					LIMIT 1',
					['uid' => trim($parentUid)],
				)
				->first();
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
		];
	}

	/**
	 * @param array{uid: string, handle: ?string, content: array<string, mixed>} $parent
	 */
	private function resolveParent(
		string $placeholder,
		array $parent,
		Locale $locale,
		string $fullPlaceholder,
	): string {
		return match ($placeholder) {
			'uid' => $this->requiredString($parent['uid'], $fullPlaceholder),
			'handle' => $this->requiredString($parent['handle'], $fullPlaceholder),
			default => $this->field($parent['content'], $placeholder, $locale, $fullPlaceholder),
		};
	}

	/**
	 * @param array<string, mixed> $content
	 */
	private function field(array $content, string $field, Locale $locale, string $placeholder): string
	{
		$value = $content[$field]['value'] ?? null;

		if (!is_array($value)) {
			throw new RoutePathError(sprintf(_('Could not resolve route placeholder: {%s}'), $placeholder));
		}

		$current = $locale;

		while ($current !== null) {
			$resolved = $this->slugValue($value[$current->id] ?? null);

			if ($resolved !== null) {
				return $resolved;
			}

			$current = $current->fallback();
		}

		$resolved = $this->slugValue($value[Field::NEUTRAL_LOCALE] ?? null);

		if ($resolved !== null) {
			return $resolved;
		}

		throw new RoutePathError(sprintf(_('Could not resolve route placeholder: {%s}'), $placeholder));
	}

	private function requiredString(mixed $value, string $placeholder): string
	{
		if (!is_string($value) || trim($value) === '') {
			throw new RoutePathError(sprintf(_('Could not resolve route placeholder: {%s}'), $placeholder));
		}

		return $value;
	}

	private function slugValue(mixed $value): ?string
	{
		if (!is_string($value) && !is_int($value) && !is_float($value)) {
			return null;
		}

		$slug = $this->slugify((string) $value);

		return $slug === '' ? null : $slug;
	}

	private function slugify(string $value): string
	{
		$value = trim(preg_replace('/\s+/', '-', $value) ?? '');
		$value = substr($value, 0, 255);
		$value = strtolower($value);
		$value = preg_replace('/[^A-Za-z0-9_-]+/', '', $value) ?? '';
		$value = preg_replace('/--+/', '-', $value) ?? '';

		return trim($value, '-');
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
