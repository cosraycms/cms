<?php

declare(strict_types=1);

namespace Cosray;

use Celema\Core\App;
use Celema\Core\Factory\Factory;
use Celema\Quma\Database;
use Celema\Router\Group;
use Celema\Router\Route;
use Closure;
use Cosray\Controller\Media;
use Cosray\Controller\Page;
use Cosray\Controller\Panel;
use Cosray\Middleware\InitRequest;
use Cosray\Middleware\PanelAuth;
use Cosray\Middleware\PanelLocale;
use Cosray\Middleware\Session;

class Routes
{
	protected string $panelPath;
	protected InitRequest $initRequestMiddlware;
	protected Session $session;
	protected bool $frontendSession;

	/**
	 * @param list<Closure(App): void> $pluginRoutes
	 * @param list<array{pattern: string, endpoint: mixed, template: string, name: string}> $panelPages
	 */
	public function __construct(
		protected Config $config,
		protected Database $db,
		protected Factory $factory,
		protected array $pluginRoutes = [],
		protected array $panelPages = [],
	) {
		$this->panelPath = $config->panel->path;
		$this->frontendSession = $config->session->enabled;
		$this->initRequestMiddlware = new InitRequest($config);
		$this->session = new Session($this->config, $this->db);
	}

	public function add(App $app): void
	{
		$sessionIfEnabled = [
			$app->get('/', [Page::class, 'catchall'], 'cms.index.get'),
			$app->post('/', [Page::class, 'catchall'], 'cms.index.post'),
			$app->get('/preview/...slug', [Page::class, 'preview'], 'cms.preview.catchall'),
		];

		// Rendition fallback: the web server serves existing cache files
		// natively; only misses reach PHP and get generated once.
		$app->get(
			'/' . trim($this->config->path->cache, '/') . '/...slug',
			[Media::class, 'cache'],
			'cms.media.cache',
		);

		$app->post(
			'/media/{mediatype:(image|file|video)}',
			[Media::class, 'upload'],
			'cms.media.upload',
		)->middleware($this->session);

		$app->get('/media/library', [Media::class, 'library'], 'cms.media.library')
			->middleware($this->session);

		$app->get('/media/{uid}', [Media::class, 'detail'], 'cms.media.detail')
			->middleware($this->session);

		$app->put('/media/{uid}', [Media::class, 'updateMeta'], 'cms.media.meta')
			->middleware($this->session);

		$app->delete('/media/{uid}', [Media::class, 'delete'], 'cms.media.delete')
			->middleware($this->session);

		$this->addPanel($app);

		foreach ($this->pluginRoutes as $addRoutes) {
			$addRoutes($app);
		}

		if ($this->frontendSession) {
			foreach ($sessionIfEnabled as $route) {
				$route->middleware($this->session);
			}
		}
	}

	public function catchallRoute(): Route
	{
		$catchallRoute = Route::map(
			['GET', 'POST'],
			'/...slug',
			[Page::class, 'catchall'],
			'cms.catchall',
		)->middleware($this->initRequestMiddlware);

		if ($this->frontendSession) {
			$catchallRoute->middleware($this->session);
		}

		return $catchallRoute;
	}

	protected function addPanel(App $app): void
	{
		$app->group(
			$this->panelPath,
			function (Group $panel) use ($app) {
				$renderers = new PanelRenderers($app);
				$panelAuth = new PanelAuth(
					$this->config,
					new Users($this->db),
					$this->factory,
				);
				// PanelLocale runs after Session so the user attribute (and
				// with it the stored panel language preference) is available.
				$panel->middleware($this->session, new PanelLocale($this->config));

				$panel
					->get('/login', [Panel\Login::class, 'login'], 'login')
					->after($renderers->get('login'));
				$panel
					->post('/login', [Panel\Login::class, 'authenticate'], 'login.authenticate')
					->after($renderers->get('login'));
				$panel
					->post('/logout', [Panel\Login::class, 'logout'], 'logout')
					->middleware($panelAuth);
				$panel
					->post('/locale', [Panel\Preferences::class, 'locale'], 'locale')
					->middleware($panelAuth);
				$panel
					->get(
						'',
						[Panel\Index::class, 'index'],
						'index',
					)
					->middleware($panelAuth)
					->after($renderers->get('index'));
				$panel
					->get(
						'/media',
						[Panel\Media::class, 'index'],
						'media',
					)
					->middleware($panelAuth)
					->after($renderers->get('media'));
				$panel
					->get(
						'/reference/search',
						[Panel\Reference::class, 'search'],
						'reference.search',
					)
					->middleware($panelAuth);
				$panel
					->get(
						'/reference/nodes',
						[Panel\Reference::class, 'nodes'],
						'reference.nodes',
					)
					->middleware($panelAuth);
				$panel
					->get(
						'/reference/labels',
						[Panel\Reference::class, 'labels'],
						'reference.labels',
					)
					->middleware($panelAuth);
				$panel
					->get(
						'/assets/...slug',
						[Panel\Assets::class, 'asset'],
						'asset',
					);
				$panel
					->get(
						'/static/...slug',
						[Panel\Assets::class, 'staticAsset'],
						'static.asset',
					);
				$panel
					->get(
						'/vendor/{plugin:[a-z0-9-]{1,64}}/...slug',
						[Panel\Assets::class, 'vendor'],
						'vendor.asset',
					);
				$panel
					->get(
						'/collection/{collection}',
						[Panel\Collection::class, 'collection'],
						'collection',
					)
					->middleware($panelAuth)
					->after($renderers->get('collection'));
				$panel
					->get(
						'/collection/{collection}/create/{type:[A-Za-z0-9-_.]{1,64}}',
						[Panel\Editor::class, 'create'],
						'editor.create',
					)
					->middleware($panelAuth)
					->after($renderers->get('editor'));
				$panel
					->post(
						'/collection/{collection}/create/{type:[A-Za-z0-9-_.]{1,64}}',
						[Panel\Editor::class, 'store'],
						'editor.store',
					)
					->middleware($panelAuth)
					->after($renderers->get('editor-save'));
				$panel
					->post(
						'/collection/{collection}/create/{type:[A-Za-z0-9-_.]{1,64}}/paths',
						[Panel\Editor::class, 'createPaths'],
						'editor.create.paths',
					)
					->middleware($panelAuth)
					->after($renderers->get('editor-paths'));
				$panel
					->post(
						'/collection/{collection}/{node:[A-Za-z0-9-_.]{1,64}}/delete',
						[Panel\Editor::class, 'delete'],
						'editor.delete',
					)
					->middleware($panelAuth)
					->after($renderers->get('editor-save'));
				$panel
					->post(
						'/collection/{collection}/{node:[A-Za-z0-9-_.]{1,64}}/paths',
						[Panel\Editor::class, 'paths'],
						'editor.paths',
					)
					->middleware($panelAuth)
					->after($renderers->get('editor-paths'));
				$panel
					->get(
						'/collection/{collection}/{node:[A-Za-z0-9-_.]{1,64}}',
						[Panel\Editor::class, 'edit'],
						'editor',
					)
					->middleware($panelAuth)
					->after($renderers->get('editor'));
				$panel
					->post(
						'/collection/{collection}/{node:[A-Za-z0-9-_.]{1,64}}',
						[Panel\Editor::class, 'save'],
						'editor.save',
					)
					->middleware($panelAuth)
					->after($renderers->get('editor-save'));

				foreach ($this->panelPages as $page) {
					$panel
						->get($page['pattern'], $page['endpoint'], $page['name'])
						->middleware($panelAuth)
						->after($renderers->get($page['template']));
				}
			},
			'cms.panel.',
		);
	}
}
