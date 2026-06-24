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
use Cosray\Node\Types;
use Cosray\Panel\CollectionQuery;
use Cosray\Panel\CollectionUrls;

use function Cosray\env;

final class Editor extends Panel
{
	private const string LEGACY_PANEL_PATH = '/panel';
	private const int LIMIT_DEFAULT = 50;
	private const int LIMIT_MAX = 250;

	public function edit(string $collection, string $node): array
	{
		[$name] = $this->collection($collection);
		$query = $this->queryState();

		return $this->editorContext(
			mode: 'edit',
			name: $name,
			collection: $collection,
			node: $node,
			type: null,
			query: $query,
		);
	}

	public function create(string $collection, string $type): array
	{
		[$name, $obj] = $this->collection($collection);

		if (!in_array($type, $this->blueprintHandles($obj), true)) {
			throw new HttpNotFound($this->request);
		}

		$query = $this->queryState();

		return $this->editorContext(
			mode: 'create',
			name: $name,
			collection: $collection,
			node: null,
			type: $type,
			query: $query,
		);
	}

	/** @return array{string, CmsCollection} */
	private function collection(string $collection): array
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

		return [$ref->meta->label, $obj];
	}

	/** @return list<string> */
	private function blueprintHandles(CmsCollection $collection): array
	{
		$types = $this->container->get(Types::class);
		assert($types instanceof Types, 'The node type service must be available');
		$handles = [];

		foreach ($collection->blueprints() as $blueprint) {
			$handles[] = (string) $types->get($blueprint, 'handle');
		}

		return $handles;
	}

	private function queryState(): CollectionQuery
	{
		$offset = $this->intParam('offset', 0, min: 0);
		$limit = $this->intParam('limit', self::LIMIT_DEFAULT, min: 1, max: self::LIMIT_MAX);
		$dir = strtolower($this->stringParam('dir'));

		if ($dir !== '' && !in_array($dir, ['asc', 'desc'], true)) {
			throw new HttpBadRequest($this->request);
		}

		$parent = $this->stringParam('parent');

		return new CollectionQuery(
			q: $this->stringParam('q'),
			sort: $this->stringParam('sort'),
			dir: $dir,
			offset: $offset,
			limit: $limit,
			parent: $parent === '' ? null : $parent,
		);
	}

	private function editorContext(
		string $mode,
		string $name,
		string $collection,
		?string $node,
		?string $type,
		CollectionQuery $query,
	): array {
		return $this->context([
			'mode' => $mode,
			'name' => $name,
			'slug' => $collection,
			'nodeUid' => $node,
			'type' => $type,
			'parent' => $query->parent,
			'queryState' => $query,
			'links' => new CollectionUrls($this->panelPath(), $collection, $query),
			'legacyLinks' => new CollectionUrls(self::LEGACY_PANEL_PATH, $collection, $query),
			'legacyApiBase' => self::LEGACY_PANEL_PATH . '/api',
			'legacyBootUrl' => self::LEGACY_PANEL_PATH . '/boot',
			'editorAssets' => $this->editorAssets(),
		]);
	}

	/** @return array{scripts: list<string>, stylesheets: list<string>}|null */
	private function editorAssets(): ?array
	{
		if ($this->config->env() === 'development') {
			return $this->editorDevAssets();
		}

		$assetDir = $this->panelDir . '/editor';
		$script = $assetDir . '/node-editor.js';

		if (!is_file($script)) {
			return null;
		}

		$panelPath = $this->panelPath();
		$css = $assetDir . '/node-editor.css';

		return [
			'scripts' => ["{$panelPath}/assets/editor/node-editor.js"],
			'stylesheets' => is_file($css) ? ["{$panelPath}/assets/editor/node-editor.css"] : [],
		];
	}

	/** @return array{scripts: list<string>, stylesheets: list<string>} */
	private function editorDevAssets(): array
	{
		$origin = $this->editorDevOrigin();

		return [
			'scripts' => [
				"{$origin}/@vite/client",
				"{$origin}/src/islands/node-editor.ts",
			],
			'stylesheets' => [],
		];
	}

	private function editorDevOrigin(): string
	{
		$origin = env('COSRAY_PANEL_DEV_ORIGIN', null);

		if (is_string($origin) && trim($origin) !== '') {
			return rtrim(trim($origin), '/');
		}

		$scheme = env('COSRAY_PANEL_DEV_SCHEME', 'http');
		$scheme = is_string($scheme) && in_array($scheme, ['http', 'https'], true) ? $scheme : 'http';
		$port = env('COSRAY_PANEL_DEV_PORT', '2001');
		$port = is_scalar($port) && preg_match('/^[0-9]+$/', (string) $port) ? (string) $port : '2001';

		return "{$scheme}://{$this->editorDevHost()}:{$port}";
	}

	private function editorDevHost(): string
	{
		$host = $this->request->uri()->getHost();

		if ($host === '') {
			$host = $this->request->header('Host');
		}

		$host = trim(explode(':', $host)[0] ?? '');

		return preg_match('/^[A-Za-z0-9.-]+$/', $host) === 1 ? $host : 'localhost';
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
