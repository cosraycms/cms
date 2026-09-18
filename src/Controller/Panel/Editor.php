<?php

declare(strict_types=1);

namespace Cosray\Controller\Panel;

use Celema\Core\Exception\HttpBadRequest;
use Celema\Core\Exception\HttpConflict;
use Celema\Core\Exception\HttpNotFound;
use Celema\Core\Factory\Factory;
use Celema\Core\Request;
use Celema\Core\Response;
use Celema\Sire\Issue;
use Celema\Wire\Creator;
use Cosray\Actor;
use Cosray\Bootstrap;
use Cosray\Cms;
use Cosray\Collection as CmsCollection;
use Cosray\Context;
use Cosray\Exception\NoSuchField;
use Cosray\Navigation;
use Cosray\Node\Factory as NodeFactory;
use Cosray\Node\PathManager;
use Cosray\Node\RoutePathGenerator;
use Cosray\Node\Serializer;
use Cosray\Node\Store;
use Cosray\Node\Types;
use Cosray\Node\Wrapper;
use Cosray\Panel\CollectionQuery;
use Cosray\Panel\CollectionUrls;
use Cosray\Panel\FormPatch;
use Cosray\Panel\NodeUrls;
use Cosray\Panel\System;
use Cosray\Richtext\Normalizer;
use Cosray\Value\Blocks as BlocksValue;
use DateTimeImmutable;
use IntlDateFormatter;
use Throwable;

final class Editor extends Panel
{
	private const int LIMIT_DEFAULT = 50;
	private const int LIMIT_MAX = 250;

	public function edit(Context $context, Cms $cms, string $node): array
	{
		$result = $cms->node->working($node);

		if (!$result) {
			throw new HttpNotFound($this->request);
		}

		$nodeObj = Wrapper::unwrap($result);
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
			node: $data,
			context: $context,
			generatedPaths: $this->generatedPaths($context, $nodeObj, $data),
			pathSourceFields: $this->pathSourceFields($context, $nodeObj),
			meta: $this->nodeMeta($data),
		);
	}

	public function create(Context $context, Cms $cms, string $type): array
	{
		[$nodeObj, $data] = $this->blueprint($cms, $context, $type);

		return $this->editorContext(
			mode: 'create',
			node: $data,
			context: $context,
			generatedPaths: $this->generatedPaths($context, $nodeObj, $data),
			pathSourceFields: $this->pathSourceFields($context, $nodeObj),
		);
	}

	public function save(
		Context $context,
		Cms $cms,
		Factory $factory,
		string $node,
	): Response|array {
		$result = $cms->node->working($node);

		if (!$result) {
			throw new HttpNotFound($this->request);
		}

		$nodeObj = Wrapper::unwrap($result);
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

		if (!$this->complete($form)) {
			return $this->refuseIncomplete($data);
		}

		$data = $this->applyForm($data, $form);
		$store = $this->nodeStore($context, $cms);
		$links = $this->origin()['links'];
		$htmx = $this->request->hasHeader('HX-Request');
		$toDraft = $this->savesAsDraft($nodeObj, $data, $form);

		try {
			if ($toDraft) {
				$store->draft($nodeObj, $data, $context->locales(), $this->actor());
			} else {
				$store->publish($nodeObj, $data, $context->locales(), $this->actor());
			}
		} catch (HttpBadRequest $e) {
			if (!$htmx) {
				// Non-htmx fallback follows the PRG pattern; errors are
				// reported through the htmx path the panel always uses.
				return Response::create($factory)->redirect($links->edit($node), 303);
			}

			$payload = is_array($e->payload()) ? $e->payload() : [];

			return [
				'saved' => false,
				'message' => (string) ($payload['message'] ?? __('node:invalid-data')),
				'errors' => $this->issues($payload),
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
			'message' => $toDraft ? __('editor:changes-saved') : __('editor:saved'),
			'errors' => [],
			'published' => (bool) ($data['published'] ?? false),
			'renderable' => (bool) ($data['type']['renderable'] ?? false),
			'preview' => ($form['preview'] ?? null) === '1' ? $this->previewPath($node) : null,
			'draft' => $this->draftFacts($cms->node->working($node)?->meta->get('draft')),
		];
	}

	public function discard(
		Context $context,
		Cms $cms,
		Factory $factory,
		string $node,
	): Response {
		$result = $cms->node->working($node);

		if (!$result) {
			throw new HttpNotFound($this->request);
		}

		$links = $this->origin()['links'];
		$this->nodeStore($context, $cms)->discard(Wrapper::unwrap($result));
		$response = Response::create($factory);

		if (!$this->request->hasHeader('HX-Request')) {
			return $response->redirect($links->edit($node), 303);
		}

		// A followed redirect would land on the discard form's hx-swap="none"
		// and render nothing. HX-Location has htmx fetch the editor from the
		// live row into the main region itself; sourced from the discard
		// form, so the unsaved-changes guard stands aside for that request,
		// and without the form's confirmation, which the POST already asked.
		return $response->header('HX-Location', json_encode([
			'path' => $links->edit($node),
			'target' => '#main',
			'swap' => 'innerHTML show:top',
			'source' => '#node-editor-discard',
			'confirm' => false,
			'replace' => 'true',
		], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
	}

	/**
	 * A published node that stays published takes a plain save as its
	 * working copy. Everything else — an unpublished node, the switch
	 * turned off, or an explicit publish — writes the live row.
	 */
	private function savesAsDraft(object $node, array $data, array $form): bool
	{
		return (
			(bool) ($data['type']['renderable'] ?? false)
				&& (bool) NodeFactory::meta($node, 'published')
				&& (bool) ($data['published'] ?? false)
				&& ($form['publish'] ?? null) !== '1'
		);
	}

	/**
	 * The working copy's facts for the editor: when it diverged, and who
	 * last saved it.
	 *
	 * @return ?array{since: ?string, editor: ?string}
	 */
	private function draftFacts(mixed $draft): ?array
	{
		if (!is_array($draft)) {
			return null;
		}

		$editor = $draft['editorName'] ?? null;

		return [
			'since' => $this->displayDate($draft['created'] ?? null),
			'editor' => is_string($editor) && trim($editor) !== '' ? $editor : null,
		];
	}

	private function nodeStore(Context $context, Cms $cms): Store
	{
		return new Store(
			$context->db,
			new PathManager(),
			$this->types(),
			$cms->nodeFactory()->uid(),
			factory: $cms->nodeFactory(),
			cms: $cms,
			context: $context,
		);
	}

	public function store(
		Context $context,
		Cms $cms,
		Factory $factory,
		string $type,
	): Response|array {
		[$nodeObj, $data] = $this->blueprint($cms, $context, $type);

		$form = $this->formData();

		if (!$this->complete($form)) {
			return $this->refuseIncomplete($data);
		}

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

		$store = $this->nodeStore($context, $cms);
		$links = $this->origin()['links'];

		try {
			$result = $store->create($nodeObj, $data, $context->locales(), $this->actor());
		} catch (HttpBadRequest $e) {
			if (!$this->request->hasHeader('HX-Request')) {
				return Response::create($factory)->redirect($links->create($type, $data['parent'] ?? null), 303);
			}

			$payload = is_array($e->payload()) ? $e->payload() : [];

			return [
				'saved' => false,
				'message' => (string) ($payload['message'] ?? __('node:invalid-data')),
				'errors' => $this->issues($payload),
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
		string $node,
	): Response|array {
		$origin = $this->origin();
		$result = $cms->node->working($node);

		if (!$result) {
			throw new HttpNotFound($this->request);
		}

		$nodeObj = Wrapper::unwrap($result);
		$store = $this->nodeStore($context, $cms);

		try {
			$store->delete($nodeObj, $this->actor());
		} catch (HttpConflict $e) {
			// Rendered through the editor-save view: the refusal lands in
			// the status chip, the form stays as it is.
			$payload = is_array($e->payload()) ? $e->payload() : [];

			return [
				'saved' => false,
				'message' => (string) ($payload['message'] ?? __('node:has-children')),
				'errors' => [],
				'published' => (bool) $result->meta->get('published'),
				'renderable' => (bool) $this->types()->get($nodeObj::class, 'renderable', false),
				'preview' => null,
			];
		}

		$response = Response::create($factory);

		if (!$this->request->hasHeader('HX-Request')) {
			return $response->redirect($origin['backUrl'], 303);
		}

		return $response->header('HX-Location', json_encode([
			'path' => $origin['backUrl'],
			'target' => '#frame',
			'swap' => 'innerHTML show:top',
			'source' => '#node-editor-delete',
			'confirm' => false,
		], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
	}

	public function paths(Context $context, Cms $cms, string $node): array
	{
		$result = $cms->node->working($node);

		if (!$result) {
			throw new HttpNotFound($this->request);
		}

		$nodeObj = Wrapper::unwrap($result);
		$pathsUrl = new NodeUrls($this->panelPath())->paths($node);

		if (!(bool) $this->types()->get($nodeObj::class, 'routable', false)) {
			return ['paths' => [], 'pathsUrl' => $pathsUrl];
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

		return [
			'paths' => $generator->preview($nodeObj::class, $data, $context->locales()),
			'pathsUrl' => $pathsUrl,
		];
	}

	/** Route-path preview for a not-yet-saved node, built from its blueprint. */
	public function createPaths(Context $context, Cms $cms, string $type): array
	{
		[$nodeObj, $data] = $this->blueprint($cms, $context, $type);
		$pathsUrl = new NodeUrls($this->panelPath())->createPaths($type, $data['parent'] ?? null);

		if (!(bool) $this->types()->get($nodeObj::class, 'routable', false)) {
			return ['paths' => [], 'pathsUrl' => $pathsUrl];
		}

		$form = $this->formData();
		$data = $this->applyForm($data, $form);
		$uid = $this->submittedUid($form);

		if ($uid !== null) {
			$data['uid'] = $uid;
		}

		$generator = new RoutePathGenerator($context->db, $this->types());

		return [
			'paths' => $generator->preview($nodeObj::class, $data, $context->locales()),
			'pathsUrl' => $pathsUrl,
		];
	}

	/**
	 * The layout preview of a blocks field: the field as the site renders
	 * it, built from the submitted form on top of the working copy. Nothing
	 * is validated or saved — readers clamp what they load and an empty
	 * block renders nothing, so half-finished content previews as well.
	 */
	public function blocks(
		Context $context,
		Cms $cms,
		string $node,
		string $field,
	): array {
		$result = $cms->node->working($node);

		if (!$result) {
			throw new HttpNotFound($this->request);
		}

		$nodeObj = Wrapper::unwrap($result);
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

		return $this->blocksPreview($context, $cms, $nodeObj, $data, $field);
	}

	/** The layout preview for a not-yet-saved node, built from its blueprint. */
	public function createBlocks(
		Context $context,
		Cms $cms,
		string $type,
		string $field,
	): array {
		[$nodeObj, $data] = $this->blueprint($cms, $context, $type);

		return $this->blocksPreview($context, $cms, $nodeObj, $data, $field);
	}

	private function blocksPreview(
		Context $context,
		Cms $cms,
		object $node,
		array $data,
		string $field,
	): array {
		$data = $this->applyForm($data, $this->formData());

		if (is_array($data['content'] ?? null)) {
			// The renderer reads the canonical richtext document the save
			// would store, not the envelope as the editor submits it.
			$data['content'] = new Normalizer()->content($data['content']);
		}

		$hydrated = $cms->nodeFactory()->create($node::class, $context, $cms, $data);

		try {
			$value = NodeFactory::fieldFor($hydrated, $field)->value();
		} catch (NoSuchField) {
			throw new HttpNotFound($this->request);
		}

		if (!$value instanceof BlocksValue) {
			throw new HttpNotFound($this->request);
		}

		$locales = $context->locales();
		$requested = $this->request->param('locale', '');
		$locale = is_string($requested) && $locales->exists($requested)
			? $locales->get($requested)
			: $locales->getDefault();

		return [
			'html' => $context->withLocale($locale, $value->render(...)),
			'stylesheet' => (string) file_get_contents(dirname(__DIR__, 3) . '/resources/blocks.css'),
			'locale' => $locale->id,
		];
	}

	/**
	 * The editor form renders a sentinel input as its LAST control; a
	 * submission without it lost its tail — typically a form-encoded POST
	 * silently truncated by PHP's max_input_vars, or a mangled JSON body.
	 */
	private function complete(array $form): bool
	{
		return ($form['_complete'] ?? null) === '1';
	}

	/**
	 * Saving an incomplete submission would silently delete the missing
	 * fields (entries rows are replaced wholesale), so this is a hard
	 * stop, not a validation issue.
	 */
	private function refuseIncomplete(array $data): array
	{
		if (!$this->request->hasHeader('HX-Request')) {
			throw new HttpBadRequest(
				$this->request,
				payload: ['message' => __('editor:incomplete-form')],
			);
		}

		return [
			'saved' => false,
			'message' => __('editor:incomplete-form'),
			'errors' => [],
			'published' => (bool) ($data['published'] ?? false),
			'renderable' => (bool) ($data['type']['renderable'] ?? false),
			'preview' => null,
		];
	}

	/**
	 * The issues behind a rejected save, reduced to what the error box
	 * renders: the message plus the data path locating the field — the
	 * client resolves the path to the matching form control.
	 *
	 * @param array<string, mixed> $payload
	 * @return list<array{message: string, path: list<string|int>}>
	 */
	private function issues(array $payload): array
	{
		$issues = $payload['errors'] ?? null;

		if (!is_array($issues)) {
			return [];
		}

		return array_values(array_map(
			static fn(Issue $issue): array => [
				'message' => $issue->message,
				'path' => $issue->path,
			],
			array_filter($issues, static fn(mixed $issue): bool => $issue instanceof Issue),
		));
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

	/** The path the preview overlay loads after a save: the node's working copy, by uid. */
	private function previewPath(string $uid): string
	{
		return '/preview/' . rawurlencode($uid);
	}

	/**
	 * Build a blueprint node object and its serialized create payload.
	 *
	 * @return array{0: object, 1: array<string, mixed>}
	 */
	private function blueprint(Cms $cms, Context $context, string $type): array
	{
		$registered = $this->container->tag(Bootstrap::NODE_TAG);

		if (!$registered->has($type)) {
			throw new HttpNotFound($this->request);
		}

		$class = $registered->entry($type)->definition();
		$parent = $this->stringParam('parent', $this->request->params());

		if ($parent !== '') {
			$parentNode = $cms->node->byUid($parent, published: null);
			$children = $parentNode?->meta->type->get('children', []);

			if (!$parentNode || !is_array($children) || !in_array($class, $children, true)) {
				throw new HttpNotFound($this->request);
			}
		}

		$factory = $cms->nodeFactory();
		$node = $factory->blueprint($class, $context, $cms);
		$serializer = new Serializer($this->types(), $factory->uid());
		$data = $serializer->blueprint($node, NodeFactory::fieldNamesFor($node), $context->locales());
		$data['parent'] = $parent === '' ? null : $parent;

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

	private function origin(): array
	{
		$origin = [
			'backUrl' => $this->homeUrl(),
			'backLabel' => __('nav:home'),
			'activeCollection' => null,
			'links' => new NodeUrls($this->panelPath()),
		];
		$from = $this->request->param('from', '');

		if ($from === 'dashboard' && $this->config->panel->dashboard) {
			return array_replace($origin, [
				'backLabel' => __('nav:dashboard'),
				'links' => new NodeUrls($this->panelPath(), 'dashboard'),
			]);
		}

		if (!is_string($from) || !str_starts_with($from, 'collection:')) {
			return $origin;
		}

		$slug = substr($from, strlen('collection:'));
		$ref = $this->navigation()->refs()[$slug] ?? null;

		if ($ref === null) {
			return $origin;
		}

		$obj = new Creator($this->container)->create(
			$ref->class,
			predefinedTypes: [Request::class => $this->request],
		);
		assert($obj instanceof CmsCollection, 'The origin must resolve a collection');
		$query = $this->queryState($obj);

		return [
			'backUrl' => new CollectionUrls($this->panelPath(), $slug, $query)->back(),
			'backLabel' => __($ref->meta->label),
			'activeCollection' => $slug,
			'links' => new NodeUrls($this->panelPath(), $from, $query),
		];
	}

	private function actor(): Actor
	{
		try {
			$id = $this->request->get('session')->authenticatedUserId();
		} catch (Throwable) {
			$id = null;
		}

		return $id ? new Actor((int) $id) : Actor::system();
	}

	private function types(): Types
	{
		$types = $this->container->get(Types::class);
		assert($types instanceof Types, 'The node type service must be available');

		return $types;
	}

	private function queryState(CmsCollection $collection): CollectionQuery
	{
		$params = $this->request->param('list', []);

		if (!is_array($params)) {
			throw new HttpBadRequest($this->request);
		}

		$offset = $this->intParam('offset', $params, 0, min: 0);
		$limit = $this->intParam('limit', $params, self::LIMIT_DEFAULT, min: 1, max: self::LIMIT_MAX);
		$dir = strtolower($this->stringParam('dir', $params));

		if ($dir !== '' && !in_array($dir, ['asc', 'desc'], true)) {
			throw new HttpBadRequest($this->request);
		}

		$parent = $this->stringParam('parent', $params);
		$parent = $collection->listMeta->showChildren && $parent !== '' ? $parent : null;
		$view = $this->stringParam('view', $params);
		$open = $this->openParam($params);
		$sort = $this->stringParam('sort', $params);

		if ($sort !== '' && !array_key_exists($sort, $collection->sorts())) {
			$sort = '';
		}
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
			q: $this->stringParam('q', $params),
			sort: $sort,
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
		array $node,
		Context $context,
		array $generatedPaths = [],
		array $pathSourceFields = [],
		array $meta = [],
	): array {
		$locales = array_map(
			static fn($locale) => [
				'id' => $locale->id,
				'title' => $locale->title,
				'fallback' => $locale->fallback,
			],
			iterator_to_array($context->locales(), false),
		);

		return $this->context([
			...$this->origin(),
			'mode' => $mode,
			'node' => $node,
			'locales' => $locales,
			'defaultLocale' => $context->locales()->getDefault()->id,
			'system' => new System($this->config, $context->locales())->payload(),
			'generatedPaths' => $generatedPaths,
			'pathSourceFields' => $pathSourceFields,
			'meta' => $meta,
			'inspectorCollapsed' => $this->request->cookie('cosray_inspector', '') === 'collapsed',
		]);
	}

	/**
	 * The inspector's fact rows: creation date in the panel locale and the
	 * last editor's display name. Only an existing node has either.
	 *
	 * @return array{created: ?string, editor: ?string}
	 */
	private function nodeMeta(array $data): array
	{
		return [
			'created' => $this->displayDate($data['created'] ?? null),
			'editor' => $this->userLabel($data['editor'] ?? null),
			'draft' => $this->draftFacts($data['draft'] ?? null),
		];
	}

	private function displayDate(mixed $value): ?string
	{
		if (!is_string($value) || trim($value) === '') {
			return null;
		}

		$timezone = $this->config->app->timezone;

		try {
			$date = new DateTimeImmutable($value, $timezone);
		} catch (Throwable) {
			return null;
		}

		$formatter = new IntlDateFormatter(
			$this->localeId(),
			IntlDateFormatter::MEDIUM,
			IntlDateFormatter::NONE,
			$timezone,
		);
		$formatted = $formatter->format($date->getTimestamp());

		return $formatted === false ? null : $formatted;
	}

	private function userLabel(mixed $user): ?string
	{
		if (!is_array($user)) {
			return null;
		}

		$data = $user['data'] ?? null;

		if (is_string($data)) {
			$data = json_decode($data, true);
		}

		$candidates = [
			is_array($data) ? $data['name'] ?? null : null,
			$user['username'] ?? null,
			$user['email'] ?? null,
		];

		foreach ($candidates as $candidate) {
			if (is_string($candidate) && trim($candidate) !== '') {
				return $candidate;
			}
		}

		return null;
	}

	private function intParam(
		string $key,
		array $params,
		int $default,
		int $min,
		?int $max = null,
	): int {
		$value = $params[$key] ?? $default;

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
	private function openParam(array $params): array
	{
		$value = $params['open'] ?? '';

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

	private function stringParam(string $key, array $params): string
	{
		$value = $params[$key] ?? '';

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
