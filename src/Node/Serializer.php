<?php

declare(strict_types=1);

namespace Cosray\Node;

use Cosray\Assets\Repository;
use Cosray\Exception\RuntimeException;
use Cosray\Field\Definitions;
use Cosray\Field\Fieldsets;
use Cosray\Locales;
use Cosray\Richtext\Scanner;
use Cosray\Uid;
use ReflectionMethod;

class Serializer
{
	public function __construct(
		private readonly Types $types,
		private readonly Uid $uid,
		private readonly ?Repository $assets = null,
		private readonly ?UrlPaths $paths = null,
	) {}

	public function content(object $node, array $rawData, array $fieldNames): array
	{
		$content = [];

		foreach ($fieldNames as $fieldName) {
			$field = Factory::fieldFor($node, $fieldName);
			$structure = $field->structure();
			$content[$fieldName] = array_merge($structure, $rawData['content'][$fieldName] ?? []);
			$content[$fieldName]['type'] = $structure['type'];
		}

		return $content;
	}

	public function data(object $node, array $rawData, array $fieldNames): array
	{
		$class = $node::class;

		return [
			'uid' => $rawData['uid'],
			'handle' => $rawData['handle'] ?? null,
			'published' => $rawData['published'],
			'hidden' => $rawData['hidden'],
			'locked' => $rawData['locked'],
			'created' => $rawData['created'],
			'changed' => $rawData['changed'],
			'deleted' => $rawData['deleted'],
			'paths' => $rawData['paths'],
			'parent' => $rawData['parent'] ?? null,
			'type' => $this->resolveType($class, $rawData['type_handle'] ?? null),
			'editor' => [
				'uid' => $rawData['editor_uid'],
				'email' => $rawData['editor_email'],
				'username' => $rawData['editor_username'],
				'data' => $rawData['editor_data'],
			],
			'creator' => [
				'uid' => $rawData['creator_uid'],
				'email' => $rawData['creator_email'],
				'username' => $rawData['creator_username'],
				'data' => $rawData['creator_data'],
			],
			'content' => $this->content($node, $rawData, $fieldNames),
			'deletable' => $this->resolveDeletable($node),
		];
	}

	public function blueprint(
		object $node,
		array $fieldNames,
		Locales $locales,
		array $values = [],
	): array {
		$content = [];
		$paths = [];

		foreach ($fieldNames as $fieldName) {
			$field = Factory::fieldFor($node, $fieldName);
			$content[$fieldName] = $field->structure($values[$fieldName] ?? null);
		}

		$class = $node::class;
		$schema = $this->types->schemaOf($class);

		foreach ($locales as $locale) {
			$paths[$locale->id] = '';
		}

		$result = [
			'title' => __('node:new-document') . ' ' . __((string) $schema->label),
			'fields' => $this->fields($node, $fieldNames),
			'fieldsets' => $this->fieldsets($node, $fieldNames),
			'uid' => $this->uid->generate(),
			'handle' => null,
			'published' => false,
			'hidden' => false,
			'locked' => false,
			'deletable' => $this->resolveDeletable($node),
			'content' => $content,
			'type' => $this->resolveType($class),
			'paths' => $paths,
			'generatedPaths' => [],
		];

		if ($schema->routable) {
			$result['route'] = $schema->route;
		}

		return $result;
	}

	public function fields(object $node, array $fieldNames): array
	{
		$fields = [];
		$ownerType = (string) $this->types->get($node::class, 'handle');

		foreach ($this->orderedNames($node, $fieldNames) as $fieldName) {
			$properties = Factory::fieldFor($node, $fieldName)->properties();
			// The owning node type, so controls that query other nodes (the
			// reference picker) can scope to this field's schema.
			$properties['ownerType'] = $ownerType;
			$fields[] = $properties;
		}

		return $fields;
	}

	/** @return list<array{name: string, label: ?string, description: ?string, width: int, fields: list<string>}> */
	public function fieldsets(object $node, array $fieldNames): array
	{
		return Fieldsets::serialize(
			Factory::fieldsetsFor($node),
			$this->orderedNames($node, $fieldNames),
			Factory::fieldsFor($node),
			$node::class,
		);
	}

	public function read(object $node, array $rawData, array $fieldNames): array
	{
		$data = $this->data($node, $rawData, $fieldNames);

		return array_merge([
			'title' => $this->resolveTitle($node),
			'uid' => $rawData['uid'],
			'fields' => $this->fields($node, $fieldNames),
			'fieldsets' => $this->fieldsets($node, $fieldNames),
			'assets' => $this->assetMap($rawData['content'] ?? null),
			'nodePaths' => $this->pathMap($rawData['content'] ?? null),
		], $data);
	}

	/**
	 * Locale-to-path maps for every node uid the content references
	 * (richtext `link.node`); consumers resolve internal links through
	 * this map, mirroring the assets map.
	 *
	 * @return array<string, array<string, string>>
	 */
	public function pathMap(mixed $content): array
	{
		if ($this->paths === null || !is_array($content)) {
			return [];
		}

		$map = [];

		foreach (Scanner::scanContent($content)['nodes'] as $uid) {
			$paths = $this->paths->map($uid);

			if ($paths !== []) {
				$map[$uid] = $paths;
			}
		}

		return $map;
	}

	/**
	 * Resolved catalog data for every asset uid the content references.
	 * Content items stay canonical `{uid, meta?}`; consumers (headless
	 * JSON readers, the panel editor) resolve uids through this map.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function assetMap(mixed $content): array
	{
		if ($this->assets === null || !is_array($content)) {
			return [];
		}

		$uids = array_values(array_unique(array_merge(
			Repository::collectUids($content),
			Scanner::scanContent($content)['assets'],
		)));
		$this->assets->preload($uids);
		$map = [];

		foreach ($uids as $uid) {
			$asset = $this->assets->get($uid);

			if (!$asset) {
				continue;
			}

			$map[$uid] = [
				'filename' => $asset->filename,
				'url' => $asset->path(),
				'thumbUrl' => $asset->resizable() ? $asset->sizePath('thumb') : $asset->path(),
				'previewUrl' => $asset->resizable() ? $asset->sizePath('preview') : $asset->path(),
				'kind' => $asset->kind,
				'mime' => $asset->mime,
				'width' => $asset->width,
				'height' => $asset->height,
				'meta' => $asset->meta,
			];
		}

		return $map;
	}

	public function resolveTitle(object $node): string
	{
		$proxy = $node instanceof Wrapper
			? $node
			: new Wrapper(
				Wrapper::unwrap($node),
				Factory::fieldNamesFor($node),
				$this->types,
			);

		return $proxy->title();
	}

	/** @return list<string> */
	private function orderedNames(object $node, array $fieldNames): array
	{
		$order = $this->types->get($node::class, 'fieldOrder');

		if ($order === null && method_exists($node, 'order')) {
			$order = $node->order();
		}

		if (!is_array($order)) {
			return array_values($fieldNames);
		}

		$class = $node::class;
		$ordered = [];

		foreach ($order as $name) {
			if (!is_string($name)) {
				throw new RuntimeException("Field order for '{$class}' must contain field names only.");
			}

			if (!in_array($name, $fieldNames, true)) {
				// A defined field outside this serialization subset is
				// tolerated; a name unknown to the class is a typo.
				if (Definitions::for($class)->field($name) === null) {
					throw new RuntimeException(
						"Field order for '{$class}' references unknown field '{$name}'.",
					);
				}

				continue;
			}

			if (in_array($name, $ordered, true)) {
				throw new RuntimeException("Field order for '{$class}' repeats field '{$name}'.");
			}

			$ordered[] = $name;
		}

		return [...$ordered, ...array_values(array_diff($fieldNames, $ordered))];
	}

	/**
	 * @param class-string $class
	 * @return array<string, mixed>
	 */
	private function resolveType(string $class, ?string $handle = null): array
	{
		$schema = $this->types->schemaOf($class);

		$type = array_merge([
			'handle' => $handle ?? $schema->handle,
			'routable' => $schema->routable,
			'renderable' => $schema->renderable,
			'class' => $class,
		], $schema->properties());

		// The schema is cached per class, so its display strings are raw ids;
		// translate them for the active locale as they are serialized.
		foreach (['label', 'badge', 'description'] as $key) {
			$value = $type[$key] ?? null;

			if (is_string($value)) {
				$type[$key] = __($value);
			}
		}

		return $type;
	}

	private function resolveDeletable(object $node): bool
	{
		if (method_exists($node, 'deletable')) {
			$method = new ReflectionMethod($node, 'deletable');

			return $method->invoke($node);
		}

		return (bool) $this->types->get($node::class, 'deletable', true);
	}
}
