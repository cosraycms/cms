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
use Cosray\Node\Node;
use Cosray\Node\Types;
use Cosray\Panel\CollectionQuery;
use Cosray\Panel\CollectionUrls;

final class Editor extends Panel
{
	private const string LEGACY_PANEL_PATH = '/panel';
	private const int LIMIT_DEFAULT = 50;
	private const int LIMIT_MAX = 250;

	public function edit(string $collection, string $node): array
	{
		[$name, $obj] = $this->collection($collection);
		$query = $this->queryState($obj);

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
		$query = $this->queryState($obj);

		if (!$this->canCreate($obj, $type, $query->parent)) {
			throw new HttpNotFound($this->request);
		}

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

	private function canCreate(CmsCollection $collection, string $type, ?string $parent): bool
	{
		if ($parent === null) {
			return in_array($type, $this->blueprintHandles($collection), true);
		}

		if (!$collection->listMeta->showChildren) {
			return false;
		}

		$childHandles = array_column(
			$collection->childBlueprints($this->parentNode($collection, $parent)),
			'slug',
		);

		return in_array($type, $childHandles, true);
	}

	private function parentNode(CmsCollection $collection, string $uid): Node
	{
		$node = $collection->cms?->node->byUid($uid, published: null);

		if (!$node) {
			throw new HttpNotFound($this->request);
		}

		return $node;
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

	private function queryState(CmsCollection $collection): CollectionQuery
	{
		$offset = $this->intParam('offset', 0, min: 0);
		$limit = $this->intParam('limit', self::LIMIT_DEFAULT, min: 1, max: self::LIMIT_MAX);
		$dir = strtolower($this->stringParam('dir'));

		if ($dir !== '' && !in_array($dir, ['asc', 'desc'], true)) {
			throw new HttpBadRequest($this->request);
		}

		$parent = $this->stringParam('parent');
		$parent = $parent === '' ? null : $parent;
		$view = $this->stringParam('view');
		$open = $this->openParam('open');
		$defaultView = $collection->listMeta->showChildren && $parent === null ? 'tree' : 'list';

		if ($view === '') {
			$view = $defaultView;
		}

		if (!in_array($view, ['tree', 'list'], true)) {
			throw new HttpBadRequest($this->request);
		}

		if (!$collection->listMeta->showChildren) {
			$open = [];
		}

		return new CollectionQuery(
			q: $this->stringParam('q'),
			sort: $this->stringParam('sort'),
			dir: $dir,
			offset: $offset,
			limit: $limit,
			parent: $parent,
			view: $view,
			open: $open,
			defaultView: $defaultView,
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
			'editorAvailable' => $this->editorAvailable(),
		]);
	}

	private function editorAvailable(): bool
	{
		return $this->config->env() === 'development' || $this->hasPanelBuild();
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

	/** @return list<string> */
	private function openParam(string $key): array
	{
		$value = $this->request->param($key, '');

		if (!is_string($value)) {
			throw new HttpBadRequest($this->request);
		}

		$open = [];

		foreach (explode(',', $value) as $uid) {
			$uid = trim($uid);

			if ($uid !== '' && !in_array($uid, $open, true)) {
				$open[] = $uid;
			}
		}

		return $open;
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
