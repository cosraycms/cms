<?php

declare(strict_types=1);

namespace Cosray\Controller\Panel;

use Celemas\Core\Exception\HttpBadRequest;
use Celemas\Core\Exception\HttpNotFound;
use Celemas\Core\Request;
use Celemas\Wire\Creator;
use Cosray\Collection as CmsCollection;
use Cosray\Exception\RuntimeException;
use Cosray\Navigation;

final class Editor extends Panel
{
	private const string LEGACY_PANEL_PATH = '/panel';
	private const int LIMIT_DEFAULT = 50;
	private const int LIMIT_MAX = 250;

	public function edit(string $collection, string $node): array
	{
		$name = $this->collectionName($collection);
		$query = $this->queryState();

		return $this->context([
			'mode' => 'edit',
			'name' => $name,
			'slug' => $collection,
			'nodeUid' => $node,
			'type' => null,
			'parent' => $query['parent'],
			'queryState' => $query,
			'legacyApiBase' => self::LEGACY_PANEL_PATH . '/api',
			'legacyBootUrl' => self::LEGACY_PANEL_PATH . '/boot',
			'legacyPanelPath' => self::LEGACY_PANEL_PATH,
			'editorAssets' => $this->editorAssets(),
		]);
	}

	private function collectionName(string $collection): string
	{
		try {
			$ref = $this->navigation()->ref($collection);
		} catch (RuntimeException $e) {
			throw new HttpNotFound($this->request, previous: $e);
		}

		$creator = new Creator($this->container);
		$obj = $creator->create(
			$ref::class,
			predefinedTypes: [Request::class => $this->request],
		);
		assert($obj instanceof CmsCollection, 'The editor route must resolve a collection');

		return $ref->meta->label;
	}

	/**
	 * @return array{
	 *     q: string,
	 *     offset: int,
	 *     limit: int,
	 *     sort: string,
	 *     dir: string,
	 *     parent: ?string,
	 * }
	 */
	private function queryState(): array
	{
		$offset = $this->intParam('offset', 0, min: 0);
		$limit = $this->intParam('limit', self::LIMIT_DEFAULT, min: 1, max: self::LIMIT_MAX);
		$dir = strtolower($this->stringParam('dir'));

		if ($dir !== '' && !in_array($dir, ['asc', 'desc'], true)) {
			throw new HttpBadRequest($this->request);
		}

		$parent = $this->stringParam('parent');

		return [
			'q' => $this->stringParam('q'),
			'offset' => $offset,
			'limit' => $limit,
			'sort' => $this->stringParam('sort'),
			'dir' => $dir,
			'parent' => $parent === '' ? null : $parent,
		];
	}

	/** @return array{js: string, css: ?string}|null */
	private function editorAssets(): ?array
	{
		$assetDir = $this->panelDir . '/editor';
		$script = $assetDir . '/node-editor.js';

		if (!is_file($script)) {
			return null;
		}

		$panelPath = $this->panelPath();
		$css = $assetDir . '/node-editor.css';

		return [
			'js' => "{$panelPath}/assets/editor/node-editor.js",
			'css' => is_file($css) ? "{$panelPath}/assets/editor/node-editor.css" : null,
		];
	}

	private function intParam(
		string $key,
		int $default,
		int $min,
		?int $max = null,
	): int {
		$value = $this->request->param($key, (string) $default);

		if (is_int($value)) {
			$int = $value;
		} elseif (is_string($value) && preg_match('/^-?[0-9]+$/', $value)) {
			$int = (int) $value;
		} else {
			throw new HttpBadRequest($this->request);
		}

		if ($int < $min) {
			throw new HttpBadRequest($this->request);
		}

		if ($max !== null && $int > $max) {
			throw new HttpBadRequest($this->request);
		}

		return $int;
	}

	private function stringParam(string $key): string
	{
		$value = $this->request->param($key, '');

		if (!is_string($value)) {
			throw new HttpBadRequest($this->request);
		}

		return trim($value);
	}

	private function navigation(): Navigation
	{
		$navigation = $this->container->get(Navigation::class);
		assert($navigation instanceof Navigation, 'The navigation service must be available');

		return $navigation;
	}
}
