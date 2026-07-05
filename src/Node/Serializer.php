<?php

declare(strict_types=1);

namespace Cosray\Node;

use Cosray\Assets\Repository;
use Cosray\Field\FieldHydrator;
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
			$field = FieldHydrator::getField($node, $fieldName);
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
			$field = FieldHydrator::getField($node, $fieldName);
			$content[$fieldName] = $field->structure($values[$fieldName] ?? null);
		}

		$class = $node::class;
		$schema = $this->types->schemaOf($class);

		foreach ($locales as $locale) {
			$paths[$locale->id] = '';
		}

		$result = [
			'title' => _('Neues Dokument:') . ' ' . $schema->label,
			'fields' => $this->fields($node, $fieldNames),
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
		$orderedFields = $this->order($node, $fieldNames);
		$missingFields = array_diff($fieldNames, $orderedFields);
		$allFields = array_merge($orderedFields, $missingFields);

		foreach ($allFields as $fieldName) {
			$fields[] = FieldHydrator::getField($node, $fieldName)->properties();
		}

		return $fields;
	}

	public function read(object $node, array $rawData, array $fieldNames): array
	{
		$data = $this->data($node, $rawData, $fieldNames);

		return array_merge([
			'title' => $this->resolveTitle($node),
			'uid' => $rawData['uid'],
			'fields' => $this->fields($node, $fieldNames),
			'assets' => $this->assetMap($rawData['content'] ?? null),
			'paths' => $this->pathMap($rawData['content'] ?? null),
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
		$proxy = $node instanceof Node
			? $node
			: new Node(
				Node::unwrap($node),
				Factory::fieldNamesFor($node),
				$this->types,
			);

		return $proxy->title();
	}

	/**
	 * @return string[]
	 */
	private function order(object $node, array $fieldNames): array
	{
		$metaOrder = $this->types->get($node::class, 'fieldOrder');

		if ($metaOrder !== null) {
			return $metaOrder;
		}

		if (method_exists($node, 'order')) {
			return $node->order();
		}

		return $fieldNames;
	}

	/**
	 * @param class-string $class
	 * @return array<string, mixed>
	 */
	private function resolveType(string $class, ?string $handle = null): array
	{
		$schema = $this->types->schemaOf($class);

		return array_merge([
			'handle' => $handle ?? $schema->handle,
			'routable' => $schema->routable,
			'renderable' => $schema->renderable,
			'class' => $class,
		], $schema->properties());
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
