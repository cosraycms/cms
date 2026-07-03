<?php

declare(strict_types=1);

namespace Cosray;

use Celemas\Core\App;
use Celemas\Core\Factory\Factory;
use Celemas\Quma\Database;
use Celemas\Router\Group;
use Celemas\Router\Route;
use Closure;
use Cosray\Controller\Auth;
use Cosray\Controller\Embed;
use Cosray\Controller\Media;
use Cosray\Controller\Nodes;
use Cosray\Controller\OldPanel;
use Cosray\Controller\Page;
use Cosray\Controller\Panel;
use Cosray\Controller\User;
use Cosray\Middleware\InitRequest;
use Cosray\Middleware\PanelAuth;
use Cosray\Middleware\Session;

class Routes
{
	private const string LEGACY_PANEL_PATH = '/panel';

	protected string $panelPath;
	protected string $oldPanelApiPath;
	protected ?string $apiPath;
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
		$this->oldPanelApiPath = self::LEGACY_PANEL_PATH . '/api';
		$this->apiPath = $config->path->api;
		$this->frontendSession = $config->session->enabled;
		$this->initRequestMiddlware = new InitRequest($config);
		$this->session = new Session($this->config, $this->db);
	}

	public function add(App $app): void
	{
		$sessionIfEnabled = [
			$app->get('/', [Page::class, 'catchall'], 'cms.index.get'),
			$app->post('/', [Page::class, 'catchall'], 'cms.index.post'),
			$app->get('/media/image/...slug', [Media::class, 'image'], 'cms.media.image'),
			$app->get('/media/file/...slug', [Media::class, 'file'], 'cms.media.file'),
			$app->get('/media/video/...slug', [Media::class, 'file'], 'cms.media.video'),
			$app->get('/preview/...slug', [Page::class, 'preview'], 'cms.preview.catchall'),
		];

		$app->post(
			'/media/{mediatype:(image|file|video)}/{doctype:(node|menu)}/{uid:[A-Za-z0-9-_.]{1,64}}',
			[Media::class, 'upload'],
			'cms.media.upload',
		)->middleware($this->session);

		// TODO: remove when new panel is finished
		$this->addOldPanelApi($app, $this->session);

		$this->addApi($app);

		// TODO: remove when new panel is finished
		// OLD PANEL ROUTES
		$app->get(
			self::LEGACY_PANEL_PATH . '/boot',
			[OldPanel::class, 'boot'],
			'cms.oldpanel.boot',
		)->after(new JsonRenderer($this->factory));
		$app->get(
			self::LEGACY_PANEL_PATH
			. '/embed/{token:[A-Za-z0-9]{1,128}}/node/{type:[A-Za-z0-9-_.]{1,64}}/create',
			[Embed::class, 'create'],
			'cms.panel.embed.create',
		)->middleware($this->session);
		$app->get(
			self::LEGACY_PANEL_PATH
			. '/embed/{token:[A-Za-z0-9]{1,128}}/node/{type:[A-Za-z0-9-_.]{1,64}}/{node:[A-Za-z0-9-_.]{1,64}}',
			[Embed::class, 'node'],
			'cms.panel.embed.node',
		)->middleware($this->session);
		$app->get(
			self::LEGACY_PANEL_PATH . '/...slug',
			[OldPanel::class, 'catchall'],
			'cms.oldpanel.catchall',
		)->middleware($this->session);
		$app->get(
			self::LEGACY_PANEL_PATH,
			[OldPanel::class, 'index'],
			'cms.oldpanel',
		)->middleware($this->session);
		$app->get(
			self::LEGACY_PANEL_PATH . '/',
			[OldPanel::class, 'index'],
			'cms.oldpanel.slash',
		)->middleware($this->session);
		// END OLD PANEL ROUTES

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

	protected function addAuth(Group $api): void
	{
		$api->get('/me', [Auth::class, 'me'], 'auth.user');
		$api->post('/login', [Auth::class, 'login'], 'auth.login');
		$api->post('/token-login', [Auth::class, 'tokenLogin'], 'auth.login.token');
		$api->post('/invalidate-token', [Auth::class, 'invalidateToken'], 'auth.token.invalidate');
		$api->get('/login/token', [Auth::class, 'token'], 'auth.token');
		$api->post('/logout', [Auth::class, 'logout'], 'auth.logout');
	}

	protected function addUser(Group $api): void
	{
		$api->get('/users', [User::class, 'list'], 'users');
		$api->get('/user/{uid:[A-Za-z0-9-_.]{1,64}}', [User::class, 'get'], 'user.get');
		$api->post('/user', [User::class, 'create'], 'user.create');
		$api->put('/user/{uid:[A-Za-z0-9-_.]{1,64}}', [User::class, 'save'], 'user.save');
		$api->get('/profile', [User::class, 'profile'], 'profile.get');
		$api->put('/profile', [User::class, 'saveProfile'], 'profile.save');
	}

	protected function addSystem(Group $api): void
	{
		$api->get('/collections', [OldPanel::class, 'collections'], 'collections');
		$api->get('/collection/{collection}', [OldPanel::class, 'collection'], 'collection');
		$api->get('/nodes', [Nodes::class, 'get'], 'nodes.search.get');
		$api->post('/nodes', [Nodes::class, 'get'], 'nodes.search.post');
		$api->get('/node/{uid:[A-Za-z0-9-_.]{1,64}}', [OldPanel::class, 'node'], 'node.get');
		$api->put('/node/{uid:[A-Za-z0-9-_.]{1,64}}', [OldPanel::class, 'node'], 'node.update');
		$api->delete('/node/{uid:[A-Za-z0-9-_.]{1,64}}', [OldPanel::class, 'node'], 'node.delete');
		$api->post('/node/{type}/paths', [OldPanel::class, 'nodePaths'], 'node.paths');
		$api->post('/node/{type}', [OldPanel::class, 'createNode'], 'node.create');
		$api->get('/blueprint/{type}', [OldPanel::class, 'blueprint'], 'node.blueprint');
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
				$panel->middleware($this->session);

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
					->get(
						'',
						[Panel\Index::class, 'index'],
						'index',
					)
					->middleware($panelAuth)
					->after($renderers->get('index'));
				$panel
					->get(
						'/assets/...slug',
						[Panel\Assets::class, 'asset'],
						'asset',
					);
				$panel
					->get(
						'/build/...slug',
						[Panel\Assets::class, 'build'],
						'build.asset',
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

	protected function addOldPanelApi(App $app, Session $session): void
	{
		$app->group(
			$this->oldPanelApiPath,
			function (Group $api) use ($session) {
				$api->after(new JsonRenderer($this->factory));
				$api->middleware($session);

				$this->addAuth($api);
				$this->addUser($api);
				$this->addSystem($api);
			},
			'cms.oldpanel.api.',
		);
	}

	protected function addApi(App $app): void
	{
		if ($this->apiPath !== null) {
			$app->group(
				$this->apiPath,
				function (Group $api) {
					$api->after(new JsonRenderer($this->factory));

					$this->addAuth($api);
					$this->addUser($api);
					$this->addSystem($api);
				},
				'cms.api.',
			);
		}
	}
}
