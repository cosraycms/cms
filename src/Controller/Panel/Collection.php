<?php

declare(strict_types=1);

namespace Cosray\Controller\Panel;

use Celema\Core\Exception\HttpBadRequest;
use Celema\Core\Exception\HttpNotFound;
use Cosray\Collection\Listing;
use Cosray\Node\Types;
use Cosray\Node\Wrapper;
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
		$ref = $this->ref($collection);
		$lister = $this->listing($ref);

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

		$sorts = $lister->sorts;

		if ($sort !== '' && !array_key_exists($sort, $sorts)) {
			throw new HttpBadRequest($this->request);
		}

		$parentUid = $lister->meta->showChildren && $parent !== '' ? $parent : null;
		$defaultView = $lister->meta->showChildren && $parentUid === null ? 'tree' : 'list';

		if ($view === '') {
			$view = $defaultView;
		}

		if (!in_array($view, ['tree', 'list'], true)) {
			throw new HttpBadRequest($this->request);
		}

		if (!$lister->meta->showChildren) {
			$open = [];
		}

		$parentNode = $parentUid === null ? null : $this->parentNode($lister, $parentUid);
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

		if ($lister->meta->showChildren && $view === 'tree') {
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
			'notice' => $this->notice(),
			'page' => CollectionPage::from(
				name: __($ref->meta->label),
				urls: $urls,
				columns: $lister->columns,
				blueprints: $this->blueprints($lister),
				nodes: $nodes,
				total: $listing['total'],
				meta: $lister->meta,
				locale: $this->localeId(),
				timezone: $this->config->app->timezone,
				parentTitle: $parentTitle,
				parentType: $parentNode === null
					? null
					: __((string) $parentNode->meta->type->get('label', '')),
				parentStatus: $parentNode === null ? null : $this->nodeStatus($lister, $parentNode),
				createBlueprints: $parentNode === null ? null : $lister->childBlueprints($parentNode),
			),
		]);
	}

	/**
	 * The bulk-action result summary from the redirect's `notice` param
	 * (`key:count` pairs), reduced to display strings. Display-only, so
	 * unknown or malformed parts are dropped rather than rejected.
	 *
	 * @return list<string>
	 */
	private function notice(): array
	{
		$value = $this->request->param('notice', '');

		if (!is_string($value) || trim($value) === '') {
			return [];
		}

		// Literal ids so the i18n scanner sees every key.
		$keys = [
			'deleted' => static fn(int $n): string => __n(
				'bulk:notice-deleted',
				'bulk:notice-deleted-plural',
				$n,
			),
			'published' => static fn(int $n): string => __n(
				'bulk:notice-published',
				'bulk:notice-published-plural',
				$n,
			),
			'unpublished' => static fn(int $n): string => __n(
				'bulk:notice-unpublished',
				'bulk:notice-unpublished-plural',
				$n,
			),
			'changes-published' => static fn(int $n): string => __n(
				'bulk:notice-changes-published',
				'bulk:notice-changes-published-plural',
				$n,
			),
			'duplicated' => static fn(int $n): string => __n(
				'bulk:notice-duplicated',
				'bulk:notice-duplicated-plural',
				$n,
			),
			'skipped-children' => static fn(int $n): string => __n(
				'bulk:notice-skipped-children',
				'bulk:notice-skipped-children-plural',
				$n,
			),
			'skipped-locked' => static fn(int $n): string => __n(
				'bulk:notice-skipped-locked',
				'bulk:notice-skipped-locked-plural',
				$n,
			),
			'skipped' => static fn(int $n): string => __n(
				'bulk:notice-skipped',
				'bulk:notice-skipped-plural',
				$n,
			),
		];
		$messages = [];

		foreach (explode(',', $value) as $part) {
			[$key, $count] = array_pad(explode(':', $part, 2), 2, '');

			if (!isset($keys[$key]) || !preg_match('/^[1-9][0-9]{0,5}$/', $count)) {
				continue;
			}

			$messages[] = $keys[$key]((int) $count);
		}

		return $messages;
	}

	private function parentNode(Listing $lister, string $uid): Wrapper
	{
		$node = $lister->parent($uid);

		if (!$node) {
			throw new HttpNotFound($this->request);
		}

		return $node;
	}

	/** @return list<array{kind: string, label: string}> */
	private function nodeStatus(Listing $lister, Wrapper $node): array
	{
		$status = [];
		$meta = $lister->meta;

		if ($meta->showPublished) {
			$published = (bool) $node->meta->get('published');
			$status[] = [
				'kind' => $published ? 'published' : 'unpublished',
				'label' => $published ? __('status:published') : __('status:unpublished'),
			];

			if ($node->meta->get('draft') !== null) {
				$status[] = ['kind' => 'changes', 'label' => __('status:changes')];
			}
		}

		if ($meta->showHidden && (bool) $node->meta->get('hidden')) {
			$status[] = [
				'kind' => 'hidden',
				'label' => __('status:hidden'),
			];
		}

		if ($meta->showLocked && (bool) $node->meta->get('locked')) {
			$status[] = [
				'kind' => 'locked',
				'label' => __('status:locked'),
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
	private function blueprints(Listing $lister): array
	{
		$types = $this->types();
		$result = [];

		foreach ($lister->blueprints as $blueprint) {
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
}
