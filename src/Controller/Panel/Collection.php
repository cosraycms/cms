<?php

declare(strict_types=1);

namespace Cosray\Controller\Panel;

use Celemas\Core\Exception\HttpBadRequest;
use Celemas\Core\Exception\HttpNotFound;
use Celemas\Core\Request;
use Celemas\Wire\Creator;
use Cosray\Collection as CmsCollection;
use Cosray\Collection\Listing;
use Cosray\Exception\RuntimeException;
use Cosray\Navigation;
use Cosray\Node\Node;
use Cosray\Node\Types;
use Cosray\Panel\CollectionPage;
use Cosray\Panel\CollectionQuery;
use Cosray\Panel\CollectionTree;
use Cosray\Panel\CollectionUrls;

final class Collection extends Panel
{
	private const int LIMIT_DEFAULT = 50;
	private const int LIMIT_MAX = 250;

	public function collection(string $collection): array
	{
		$creator = new Creator($this->container);
		$navigation = $this->navigation();

		try {
			$ref = $navigation->ref($collection);
		} catch (RuntimeException $e) {
			throw new HttpNotFound($this->request, previous: $e);
		}

		$obj = $creator->create(
			$ref->class,
			predefinedTypes: [Request::class => $this->request],
		);
		assert($obj instanceof CmsCollection, 'The collection route must resolve a collection');
		$lister = new Listing($obj, $this->types());

		$offset = $this->intParam('offset', 0, min: 0);
		$limit = $this->intParam('limit', self::LIMIT_DEFAULT, min: 1, max: self::LIMIT_MAX);
		$q = $this->stringParam('q');
		$sort = $this->stringParam('sort');
		$dir = strtolower($this->stringParam('dir'));
		$parent = $this->stringParam('parent');
		$view = $this->stringParam('view');
		$open = $this->openParam('open');

		if ($dir !== '' && !in_array($dir, ['asc', 'desc'], true)) {
			throw new HttpBadRequest($this->request);
		}

		$sorts = $obj->sorts();

		if ($sort !== '' && !array_key_exists($sort, $sorts)) {
			throw new HttpBadRequest($this->request);
		}

		$parentUid = $obj->listMeta->showChildren && $parent !== '' ? $parent : null;
		$defaultView = $obj->listMeta->showChildren && $parentUid === null ? 'tree' : 'list';

		if ($view === '') {
			$view = $defaultView;
		}

		if (!in_array($view, ['tree', 'list'], true)) {
			throw new HttpBadRequest($this->request);
		}

		if (!$obj->listMeta->showChildren) {
			$open = [];
		}

		$parentNode = $parentUid === null ? null : $this->parentNode($obj, $parentUid);
		$parentTitle = $parentNode?->title();

		if ($parentTitle !== null && trim($parentTitle) === '') {
			$parentTitle = $parentUid;
		}

		$listing = $lister->list(
			offset: $offset,
			limit: $limit,
			q: $q,
			sort: $sort,
			dir: $dir,
			parent: $parentUid,
		);

		$query = new CollectionQuery(
			q: $listing['q'],
			sort: $listing['sort'],
			dir: $listing['dir'],
			offset: $listing['offset'],
			limit: $listing['limit'],
			parent: $parentUid,
			view: $view,
			open: $open,
			defaultView: $defaultView,
		);
		$nodes = $listing['nodes'];

		if ($obj->listMeta->showChildren && $view === 'tree') {
			$nodes = CollectionTree::build(
				nodes: $nodes,
				open: $open,
				children: static fn(string $uid): array => $lister->list(
					offset: 0,
					limit: self::LIMIT_MAX,
					sort: $listing['sort'],
					dir: $listing['dir'],
					parent: $uid,
				)['nodes'],
			);
		}

		$urls = new CollectionUrls($this->panelPath(), $collection, $query);

		return $this->context([
			'page' => CollectionPage::from(
				name: $ref->meta->label,
				urls: $urls,
				columns: $obj->columns(),
				sortKeys: array_keys($sorts),
				blueprints: $this->blueprints($obj),
				nodes: $nodes,
				total: $listing['total'],
				meta: $obj->listMeta,
				locale: $this->localeId(),
				timezone: $this->config->app->timezone,
				parentTitle: $parentTitle,
				parentType: $parentNode === null
					? null
					: (string) $parentNode->meta->type->get('label', ''),
				parentStatus: $parentNode === null ? null : $this->nodeStatus($obj, $parentNode),
				createBlueprints: $parentNode === null ? null : $lister->childBlueprints($parentNode),
			),
		]);
	}

	private function parentNode(CmsCollection $collection, string $uid): Node
	{
		$node = $collection->cms?->node->byUid($uid, published: null);

		if (!$node) {
			throw new HttpNotFound($this->request);
		}

		return $node;
	}

	/** @return list<array{kind: string, label: string}> */
	private function nodeStatus(CmsCollection $collection, Node $node): array
	{
		$status = [];
		$meta = $collection->listMeta;

		if ($meta->showPublished) {
			$published = (bool) $node->meta->get('published');
			$status[] = [
				'kind' => $published ? 'published' : 'draft',
				'label' => $published ? 'Published' : 'Draft',
			];
		}

		if ($meta->showHidden && (bool) $node->meta->get('hidden')) {
			$status[] = [
				'kind' => 'hidden',
				'label' => 'Hidden',
			];
		}

		if ($meta->showLocked && (bool) $node->meta->get('locked')) {
			$status[] = [
				'kind' => 'locked',
				'label' => 'Locked',
			];
		}

		return $status;
	}

	private function types(): Types
	{
		$types = $this->container->get(Types::class);
		assert($types instanceof Types, 'The node type service must be available');

		return $types;
	}

	/** @return list<array{slug: string, name: string}> */
	private function blueprints(CmsCollection $collection): array
	{
		$types = $this->types();
		$result = [];

		foreach ($collection->blueprints() as $blueprint) {
			$result[] = [
				'slug' => (string) $types->get($blueprint, 'handle'),
				'name' => (string) $types->get($blueprint, 'label'),
			];
		}

		return $result;
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
