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
use Cosray\Panel\CollectionPage;
use Cosray\Panel\CollectionQuery;
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
			$ref::class,
			predefinedTypes: [Request::class => $this->request],
		);
		assert($obj instanceof CmsCollection, 'The collection route must resolve a collection');

		$offset = $this->intParam('offset', 0, min: 0);
		$limit = $this->intParam('limit', self::LIMIT_DEFAULT, min: 1, max: self::LIMIT_MAX);
		$q = $this->stringParam('q');
		$sort = $this->stringParam('sort');
		$dir = strtolower($this->stringParam('dir'));
		$parent = $this->stringParam('parent');

		if ($dir !== '' && !in_array($dir, ['asc', 'desc'], true)) {
			throw new HttpBadRequest($this->request);
		}

		$sorts = $obj->sorts();

		if ($sort !== '' && !array_key_exists($sort, $sorts)) {
			throw new HttpBadRequest($this->request);
		}

		$listing = $obj->list(
			offset: $offset,
			limit: $limit,
			q: $q,
			sort: $sort,
			dir: $dir,
			parent: $parent === '' ? null : $parent,
		);

		$query = new CollectionQuery(
			q: $listing['q'],
			sort: $listing['sort'],
			dir: $listing['dir'],
			offset: $listing['offset'],
			limit: $listing['limit'],
			parent: $parent === '' ? null : $parent,
		);
		$urls = new CollectionUrls($this->panelPath(), $collection, $query);

		return $this->context([
			'page' => CollectionPage::from(
				name: $ref->meta->label,
				urls: $urls,
				columns: $obj->columns(),
				sortKeys: array_keys($sorts),
				blueprints: $this->blueprints($obj),
				nodes: $listing['nodes'],
				total: $listing['total'],
				meta: $obj->listMeta,
				locale: $this->localeId(),
			),
		]);
	}

	/** @return list<array{slug: string, name: string}> */
	private function blueprints(CmsCollection $collection): array
	{
		$types = $this->container->get(Types::class);
		assert($types instanceof Types, 'The node type service must be available');
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
