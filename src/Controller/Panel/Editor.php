<?php

declare(strict_types=1);

namespace Cosray\Controller\Panel;

use Celemas\Core\Exception\HttpBadRequest;
use Celemas\Core\Exception\HttpNotFound;
use Celemas\Core\Factory\Factory;
use Celemas\Core\Request;
use Celemas\Core\Response;
use Celemas\Wire\Creator;
use Cosray\Bootstrap;
use Cosray\Cms;
use Cosray\Collection as CmsCollection;
use Cosray\Collection\Listing;
use Cosray\Context;
use Cosray\Exception\RuntimeException;
use Cosray\Navigation;
use Cosray\Node\Factory as NodeFactory;
use Cosray\Node\Node;
use Cosray\Node\PathManager;
use Cosray\Node\RoutePathGenerator;
use Cosray\Node\Serializer;
use Cosray\Node\Store;
use Cosray\Node\Types;
use Cosray\Panel\CollectionQuery;
use Cosray\Panel\CollectionUrls;
use Cosray\Panel\FormPatch;
use Cosray\Panel\System;

final class Editor extends Panel
{
	private const int LIMIT_DEFAULT = 50;
	private const int LIMIT_MAX = 250;

	public function edit(Context $context, Cms $cms, string $collection, string $node): array
	{
		[$name, $obj] = $this->collection($collection);
		$query = $this->queryState($obj);
		$result = $cms->node->byUid($node, published: null);

		if (!$result) {
			throw new HttpNotFound($this->request);
		}

		$nodeObj = Node::unwrap($result);
		$serializer = new Serializer(
			$this->types(),
			$cms->nodeFactory()->uid(),
			$context->assets(),
			$context->paths(),
		);
		$data = $serializer->read(
			$nodeObj,
			NodeFactory::dataFor($nodeObj),
			NodeFactory::fieldNamesFor($nodeObj),
		);

		return $this->editorContext(
			mode: 'edit',
			name: $name,
			collection: $collection,
			node: $data,
			query: $query,
			context: $context,
			generatedPaths: $this->generatedPaths($context, $nodeObj, $data),
			pathSourceFields: $this->pathSourceFields($context, $nodeObj),
		);
	}

	public function create(Context $context, Cms $cms, string $collection, string $type): array
	{
		[$name, $obj] = $this->collection($collection);
		$query = $this->queryState($obj);

		if (!$this->canCreate($obj, $type, $query->parent)) {
			throw new HttpNotFound($this->request);
		}

		[$nodeObj, $data] = $this->blueprint($cms, $context, $type);

		return $this->editorContext(
			mode: 'create',
			name: $name,
			collection: $collection,
			node: $data,
			query: $query,
			context: $context,
			generatedPaths: $this->generatedPaths($context, $nodeObj, $data),
			pathSourceFields: $this->pathSourceFields($context, $nodeObj),
		);
	}

	public function save(
		Context $context,
		Cms $cms,
		Factory $factory,
		string $collection,
		string $node,
	): Response|array {
		[, $obj] = $this->collection($collection);
		$query = $this->queryState($obj);
		$result = $cms->node->byUid($node, published: null);

		if (!$result) {
			throw new HttpNotFound($this->request);
		}

		$nodeObj = Node::unwrap($result);
		$serializer = new Serializer(
			$this->types(),
			$cms->nodeFactory()->uid(),
			$context->assets(),
			$context->paths(),
		);
		$data = $serializer->read(
			$nodeObj,
			NodeFactory::dataFor($nodeObj),
			NodeFactory::fieldNamesFor($nodeObj),
		);

		$form = $this->formData();
		$data = $this->applyForm($data, $form);
		$store = new Store(
			$context->db,
			new PathManager(),
			$this->types(),
			$cms->nodeFactory()->uid(),
			factory: $cms->nodeFactory(),
			cms: $cms,
			context: $context,
		);
		$links = new CollectionUrls($this->panelPath(), $collection, $query);
		$htmx = $this->request->hasHeader('HX-Request');

		try {
			$store->save($nodeObj, $data, $this->request, $context->locales());
		} catch (HttpBadRequest $e) {
			if (!$htmx) {
				// Non-htmx fallback follows the PRG pattern; errors are
				// reported through the htmx path the panel always uses.
				return Response::create($factory)->redirect($links->edit($node), 303);
			}

			$payload = is_array($e->payload()) ? $e->payload() : [];

			return [
				'saved' => false,
				'message' => (string) ($payload['message'] ?? _('Incomplete or invalid data')),
				'errors' => is_array($payload['errors'] ?? null) ? $payload['errors'] : [],
				'published' => (bool) ($data['published'] ?? false),
				'renderable' => (bool) ($data['type']['renderable'] ?? false),
				'preview' => null,
			];
		}

		if (!$htmx) {
			return Response::create($factory)->redirect($links->edit($node), 303);
		}

		return [
			'saved' => true,
			'message' => _('Gespeichert'),
			'errors' => [],
			'published' => (bool) ($data['published'] ?? false),
			'renderable' => (bool) ($data['type']['renderable'] ?? false),
			'preview' => ($form['preview'] ?? null) === '1' ? $this->previewPath($cms, $node) : null,
		];
	}

	public function store(
		Context $context,
		Cms $cms,
		Factory $factory,
		string $collection,
		string $type,
	): Response|array {
		[, $obj] = $this->collection($collection);
		$query = $this->queryState($obj);

		if (!$this->canCreate($obj, $type, $query->parent)) {
			throw new HttpNotFound($this->request);
		}

		[$nodeObj, $data] = $this->blueprint($cms, $context, $type);

		$form = $this->formData();
		$patch = new FormPatch($data['fields']);
		$submitted = $form['content'] ?? [];
		$data['content'] = $patch->content(
			$data['content'],
			is_array($submitted) ? $submitted : [],
		);
		$data = $this->applySettings($data, $form);

		$uid = $this->submittedUid($form);

		if ($uid !== null) {
			// Honour the uid the create form pre-generated so media uploaded
			// to node/<uid>/ before the first save resolves to the node.
			$data['uid'] = $uid;
		}

		if ($query->parent !== null) {
			$data['parent'] = $query->parent;
		}

		$store = new Store(
			$context->db,
			new PathManager(),
			$this->types(),
			$cms->nodeFactory()->uid(),
			factory: $cms->nodeFactory(),
			cms: $cms,
			context: $context,
		);
		$links = new CollectionUrls($this->panelPath(), $collection, $query);

		try {
			$result = $store->create($nodeObj, $data, $this->request, $context->locales());
		} catch (HttpBadRequest $e) {
			if (!$this->request->hasHeader('HX-Request')) {
				return Response::create($factory)->redirect($links->create($type), 303);
			}

			$payload = is_array($e->payload()) ? $e->payload() : [];

			return [
				'saved' => false,
				'message' => (string) ($payload['message'] ?? _('Incomplete or invalid data')),
				'errors' => is_array($payload['errors'] ?? null) ? $payload['errors'] : [],
				'published' => (bool) ($data['published'] ?? false),
				'renderable' => (bool) ($data['type']['renderable'] ?? false),
				'preview' => null,
			];
		}

		// The redirect swaps the fresh edit form in; htmx follows it.
		return Response::create($factory)->redirect(
			$links->edit((string) ($result['uid'] ?? $data['uid'])),
			303,
		);
	}

	public function delete(
		Context $context,
		Cms $cms,
		Factory $factory,
		string $collection,
		string $node,
	): Response {
		[, $obj] = $this->collection($collection);
		$query = $this->queryState($obj);
		$result = $cms->node->byUid($node, published: null);

		if (!$result) {
			throw new HttpNotFound($this->request);
		}

		$store = new Store(
			$context->db,
			new PathManager(),
			$this->types(),
			$cms->nodeFactory()->uid(),
			factory: $cms->nodeFactory(),
			cms: $cms,
			context: $context,
		);
		$store->delete(Node::unwrap($result), $this->request, requireJson: false);
		$links = new CollectionUrls($this->panelPath(), $collection, $query);

		return Response::create($factory)->redirect($links->collection(), 303);
	}

	public function paths(Context $context, Cms $cms, string $collection, string $node): array
	{
		[, $obj] = $this->collection($collection);
		$query = $this->queryState($obj);
		$result = $cms->node->byUid($node, published: null);

		if (!$result) {
			throw new HttpNotFound($this->request);
		}

		$nodeObj = Node::unwrap($result);
		$links = new CollectionUrls($this->panelPath(), $collection, $query);
		$pathsUrl = $links->paths($node);

		if (!(bool) $this->types()->get($nodeObj::class, 'routable', false)) {
			return ['paths' => [], 'submitted' => [], 'pathsUrl' => $pathsUrl];
		}

		$serializer = new Serializer(
			$this->types(),
			$cms->nodeFactory()->uid(),
			$context->assets(),
			$context->paths(),
		);
		$data = $serializer->read(
			$nodeObj,
			NodeFactory::dataFor($nodeObj),
			NodeFactory::fieldNamesFor($nodeObj),
		);
		$form = $this->formData();
		$data = $this->applyForm($data, $form);

		$generator = new RoutePathGenerator($context->db, $this->types());
		$submitted = is_array($form['paths'] ?? null) ? $form['paths'] : [];

		return [
			'paths' => $generator->preview($nodeObj::class, $data, $context->locales()),
			'submitted' => $submitted,
			'pathsUrl' => $pathsUrl,
		];
	}

	/** Route-path preview for a not-yet-saved node, built from its blueprint. */
	public function createPaths(Context $context, Cms $cms, string $collection, string $type): array
	{
		[, $obj] = $this->collection($collection);
		$query = $this->queryState($obj);

		if (!$this->canCreate($obj, $type, $query->parent)) {
			throw new HttpNotFound($this->request);
		}

		$links = new CollectionUrls($this->panelPath(), $collection, $query);
		$pathsUrl = $links->createPaths($type);
		[$nodeObj, $data] = $this->blueprint($cms, $context, $type);

		if (!(bool) $this->types()->get($nodeObj::class, 'routable', false)) {
			return ['paths' => [], 'submitted' => [], 'pathsUrl' => $pathsUrl];
		}

		$form = $this->formData();
		$data = $this->applyForm($data, $form);
		$uid = $this->submittedUid($form);

		if ($uid !== null) {
			$data['uid'] = $uid;
		}

		$generator = new RoutePathGenerator($context->db, $this->types());
		$submitted = is_array($form['paths'] ?? null) ? $form['paths'] : [];

		return [
			'paths' => $generator->preview($nodeObj::class, $data, $context->locales()),
			'submitted' => $submitted,
			'pathsUrl' => $pathsUrl,
		];
	}

	/** Apply the submitted editor form (content patch + settings). */
	private function applyForm(array $data, array $form): array
	{
		$patch = new FormPatch($data['fields']);
		$submitted = $form['content'] ?? [];
		$data['content'] = $patch->content(
			$data['content'],
			is_array($submitted) ? $submitted : [],
		);

		return $this->applySettings($data, $form);
	}

	private function applySettings(array $data, array $form): array
	{
		if (array_key_exists('handle', $form)) {
			$data['handle'] = is_string($form['handle']) ? $form['handle'] : null;
		}

		if (is_array($form['paths'] ?? null)) {
			$paths = is_array($data['paths'] ?? null) ? $data['paths'] : [];

			foreach ($form['paths'] as $locale => $path) {
				if (is_string($locale) && is_string($path)) {
					$paths[$locale] = $path;
				}
			}

			$data['paths'] = $paths;
		}

		foreach (['published', 'hidden'] as $flag) {
			if (array_key_exists($flag, $form)) {
				$data[$flag] = in_array($form[$flag], ['1', 'on', true], true);
			}
		}

		if (($form['publish'] ?? null) === '1') {
			$data['published'] = true;
		}

		return $data;
	}

	/** The public path the preview overlay loads after a save. */
	private function previewPath(Cms $cms, string $uid): ?string
	{
		$result = $cms->node->byUid($uid, published: null);

		if (!$result) {
			return null;
		}

		$node = Node::unwrap($result);
		$paths = NodeFactory::dataFor($node)['paths'] ?? [];

		foreach (is_array($paths) ? $paths : [] as $path) {
			if (is_string($path) && trim($path) !== '') {
				return $path;
			}
		}

		return null;
	}

	/**
	 * Build a blueprint node object and its serialized create payload.
	 *
	 * @return array{0: object, 1: array<string, mixed>}
	 */
	private function blueprint(Cms $cms, Context $context, string $type): array
	{
		$class = $this->container
			->tag(Bootstrap::NODE_TAG)
			->entry($type)
			->definition();
		$factory = $cms->nodeFactory();
		$node = $factory->blueprint($class, $context, $cms);
		$serializer = new Serializer($this->types(), $factory->uid());
		$data = $serializer->blueprint($node, NodeFactory::fieldNamesFor($node), $context->locales());

		return [$node, $data];
	}

	/**
	 * The uid the create form pre-generated, if it is a well-formed handle;
	 * used only in create mode so uploads and the saved node share one uid.
	 */
	private function submittedUid(array $form): ?string
	{
		$uid = $form['uid'] ?? null;

		if (is_string($uid) && preg_match('/^[A-Za-z0-9](?:[A-Za-z0-9._-]{0,62}[A-Za-z0-9])?$/', $uid)) {
			return $uid;
		}

		return null;
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
			$ref->class,
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

		$lister = new Listing($collection, $this->types());
		$childHandles = array_column(
			$lister->childBlueprints($this->parentNode($collection, $parent)),
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
		$types = $this->types();
		$handles = [];

		foreach ($collection->blueprints() as $blueprint) {
			$handles[] = (string) $types->get($blueprint, 'handle');
		}

		return $handles;
	}

	private function types(): Types
	{
		$types = $this->container->get(Types::class);
		assert($types instanceof Types, 'The node type service must be available');

		return $types;
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

	/**
	 * The route paths the current node would generate, previewed in the
	 * settings pane. Empty for non-routable types and create mode.
	 *
	 * @param array<string, mixed> $data
	 * @return array<string, string>
	 */
	private function generatedPaths(Context $context, object $node, array $data): array
	{
		if (!(bool) $this->types()->get($node::class, 'routable', false)) {
			return [];
		}

		return new RoutePathGenerator($context->db, $this->types())
			->preview($node::class, $data, $context->locales());
	}

	/**
	 * The node's own content-field names its route template references, so
	 * the editor marks exactly those inputs to live-refresh the path preview.
	 *
	 * @return list<string>
	 */
	private function pathSourceFields(Context $context, object $node): array
	{
		return new RoutePathGenerator($context->db, $this->types())
			->referencedFields($this->types()->get($node::class, 'route'));
	}

	private function editorContext(
		string $mode,
		string $name,
		string $collection,
		array $node,
		CollectionQuery $query,
		Context $context,
		array $generatedPaths = [],
		array $pathSourceFields = [],
	): array {
		$locales = array_map(
			static fn($locale) => ['id' => $locale->id, 'title' => $locale->title],
			iterator_to_array($context->locales(), false),
		);

		return $this->context([
			'mode' => $mode,
			'name' => $name,
			'slug' => $collection,
			'node' => $node,
			'locales' => $locales,
			'defaultLocale' => $context->locales()->getDefault()->id,
			'system' => new System($this->config, $context->locales())->payload(),
			'queryState' => $query,
			'links' => new CollectionUrls($this->panelPath(), $collection, $query),
			'generatedPaths' => $generatedPaths,
			'pathSourceFields' => $pathSourceFields,
		]);
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
