<?php

declare(strict_types=1);

namespace Cosray\Controller;

use Celemas\Container\Container;
use Celemas\Core\Exception\HttpBadRequest;
use Celemas\Core\Exception\HttpNotFound;
use Celemas\Core\Factory\Factory;
use Celemas\Core\Request;
use Celemas\Core\Response;
use Celemas\Wire\Creator;
use Cosray\Cms;
use Cosray\Config;
use Cosray\Context;
use Cosray\Locales;
use Cosray\Middleware\Permission;
use Cosray\Navigation;
use Cosray\Node\Factory as NodeFactory;
use Cosray\Node\Node;
use Cosray\Node\PathManager;
use Cosray\Node\Serializer;
use Cosray\Node\Store;
use Cosray\Node\Types;
use Cosray\Plugin;

class OldPanel
{
	private const int LIMIT_DEFAULT = 50;
	private const int LIMIT_MAX = 250;

	protected string $publicPath;

	public function __construct(
		protected readonly Request $request,
		protected readonly Config $config,
		protected readonly Container $container,
		protected readonly Locales $locales,
		protected readonly Types $types,
	) {
		$this->publicPath = $config->path->public;
	}

	public function boot(): array
	{
		$config = $this->config;
		$localesList = array_map(
			static fn($locale) => [
				'id' => $locale->id,
				'title' => $locale->title,
				'fallback' => $locale->fallback,
			],
			iterator_to_array($this->locales),
			[], // Add an empty array to remove the assoc array keys
			//    See: https://www.php.net/manual/en/function.array-map.php#refsect1-function.array-map-returnvalues
		);

		return [
			'locales' => $localesList,
			'locale' => $this->locales->getDefault()->id, // TODO: set the correct user locale
			'defaultLocale' => $this->locales->getDefault()->id,
			'debug' => $config->debug(),
			'env' => $config->env(),
			'csrfToken' => '', // TODO: real token
			'logo' => $config->panel->logo,
			'theme' => $this->themeStylesheets(),
			'api' => $config->path->api,
			'assets' => $config->path->assets,
			'cache' => $config->path->cache,
			'prefix' => $config->path->prefix,
			'sessionExpires' => $config->session->options['gc_maxlifetime'],
			'transliterate' => [],
			'allowedFiles' => [
				'file' => array_merge(...array_values($config->upload->file)),
				'image' => array_merge(...array_values($config->upload->image)),
				'video' => array_merge(...array_values($config->upload->video)),
			],
		];
	}

	public function index(Factory $factory): Response
	{
		return Response::create($factory)->file($this->getPanelIndex());
	}

	public function catchall(Factory $factory, string $slug): Response
	{
		$file = $this->publicPath . $this->config->panel->path . '/' . $slug;

		if (is_file($file)) {
			return Response::create($factory)->file($file);
		}

		return Response::create($factory)->file($this->getPanelIndex());
	}

	#[Permission('panel')]
	public function collections(): array
	{
		return $this->flattenCollections($this->navigation()->payload());
	}

	#[Permission('panel')]
	public function collection(string $collection): array
	{
		$creator = new Creator($this->container);
		$ref = $this->navigation()->ref($collection);
		$obj = $creator->create(
			$ref::class,
			predefinedTypes: [Request::class => $this->request],
		);
		$blueprints = [];
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

		foreach ($obj->blueprints() as $blueprint) {
			$blueprints[] = [
				'slug' => (string) $this->types->get($blueprint, 'handle'),
				'name' => (string) $this->types->get($blueprint, 'label'),
			];
		}

		return [
			'name' => $ref->meta->label,
			'meta' => $ref->meta->array(),
			'slug' => $collection,
			'header' => $obj->header(),
			'showPublished' => $obj->listMeta->showPublished,
			'showHidden' => $obj->listMeta->showHidden,
			'showLocked' => $obj->listMeta->showLocked,
			'showChildren' => $obj->listMeta->showChildren,
			'total' => $listing['total'],
			'offset' => $listing['offset'],
			'limit' => $listing['limit'],
			'q' => $listing['q'],
			'sort' => $listing['sort'],
			'dir' => $listing['dir'],
			'sorts' => array_keys($sorts),
			'nodes' => $listing['nodes'],
			'blueprints' => $blueprints,
		];
	}

	#[Permission('panel')]
	public function blueprint(string $type, Context $context, Cms $cms): array
	{
		$content = [];
		$defaults = $this->request->param('content', null);

		if ($defaults !== null) {
			// TODO: check security concerns
			$content = json_decode($defaults, true);
		}

		$factory = $cms->nodeFactory();
		$class = $this->container
			->tag(Plugin::NODE_TAG)
			->entry($type)
			->definition();
		$obj = $factory->blueprint($class, $context, $cms);

		$serializer = new Serializer(
			$factory->hydrator(),
			$this->types,
			$factory->uid(),
		);

		return $serializer->blueprint(
			$obj,
			NodeFactory::fieldNamesFor($obj),
			$context->locales(),
			$content,
		);
	}

	#[Permission('panel')]
	public function createNode(
		string $type,
		Context $context,
		Cms $cms,
		Factory $factory,
	): Response {
		if ($this->request->header('Content-Type') !== 'application/json') {
			throw new HttpBadRequest($this->request);
		}

		$data = $this->request->json();
		$class = $this->container
			->tag(Plugin::NODE_TAG)
			->entry($type)
			->definition();
		$obj = $cms->nodeFactory()->create($class, $context, $cms, $data);

		$store = new Store($context->db, new PathManager(), $this->types, $cms->nodeFactory()->uid());
		$result = $store->create($obj, $data, $this->request, $context->locales());

		return new Response(
			$factory
				->response()
				->withStatus(201)
				->withHeader('Content-Type', 'application/json'),
		)->body(json_encode($result));
	}

	#[Permission('panel')]
	public function node(Context $context, Cms $cms, Factory $factory, string $uid): Response
	{
		$result = $cms->node->byUid($uid, published: null);

		if (!$result) {
			throw new HttpNotFound($this->request);
		}

		$node = Node::unwrap($result);
		$nodeFactory = $cms->nodeFactory();
		$serializer = new Serializer($nodeFactory->hydrator(), $this->types, $nodeFactory->uid());
		$store = new Store($context->db, new PathManager(), $this->types, $nodeFactory->uid());
		$method = $this->request->method();

		$result = match ($method) {
			'GET' => $serializer->read(
				$node,
				NodeFactory::dataFor($node),
				NodeFactory::fieldNamesFor($node),
			),
			'PUT' => $this->saveNode($node, $store, $context),
			'DELETE' => $store->delete($node, $this->request),
			default => throw new HttpBadRequest($this->request),
		};

		$content = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

		return new Response(
			$factory
				->response()
				->withStatus($method === 'POST' ? 201 : 200)
				->withHeader('Content-Type', 'application/json'),
		)->body($content);
	}

	private function saveNode(object $node, Store $store, Context $context): array
	{
		if ($this->request->header('Content-Type') !== 'application/json') {
			throw new HttpBadRequest($this->request);
		}

		return $store->save($node, $this->request->json(), $this->request, $context->locales());
	}

	private function themeStylesheets(): array
	{
		return $this->config->panel->theme;
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

	protected function getPanelIndex(): string
	{
		return $this->publicPath . $this->config->panel->path . '/index.html';
	}

	/**
	 * Keep the old panel collections API backward-compatible.
	 *
	 * The legacy Svelte panel expects a flat sequence of section and collection items.
	 * Newer navigation payloads can be nested, so we flatten them depth-first here.
	 *
	 * @param list<array<string, mixed>> $items
	 * @return list<array<string, mixed>>
	 */
	private function flattenCollections(array $items): array
	{
		$result = [];

		foreach ($items as $item) {
			if (!is_array($item)) {
				continue;
			}

			$type = $item['type'] ?? null;
			$meta = $item['meta'] ?? [];
			$meta = is_array($meta) ? $meta : [];

			if ($type === 'section') {
				$result[] = [
					'type' => 'section',
					'name' => (string) ($item['name'] ?? ''),
					'meta' => $meta,
					'children' => [],
				];
				$children = $item['children'] ?? [];

				if (is_array($children)) {
					$result = array_merge($result, $this->flattenCollections($children));
				}

				continue;
			}

			if ($type !== 'collection') {
				continue;
			}

			$result[] = [
				'type' => 'collection',
				'slug' => (string) ($item['slug'] ?? ''),
				'name' => (string) ($item['name'] ?? ''),
				'meta' => $meta,
				'children' => [],
			];
		}

		return $result;
	}

	private function navigation(): Navigation
	{
		$navigation = $this->container->get(Navigation::class);
		assert($navigation instanceof Navigation, 'The navigation service must be available');

		return $navigation;
	}
}
