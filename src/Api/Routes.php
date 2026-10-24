<?php

declare(strict_types=1);

namespace Cosray\Api;

use Celema\Core\App;
use Celema\Router\Group;
use Celema\Router\Route;
use Cosray\Users;

final class Routes
{
	public function __construct(
		private readonly Headers $headers,
		private readonly Problems $problems,
		private readonly Users $users,
	) {}

	public function add(App $app, string $path): void
	{
		$app->group(
			$path,
			function (Group $api): void {
				$api->middleware($this->headers, $this->problems, new Authenticate($this->users), new CheckLocale());

				$uid = '{uid:[A-Za-z0-9-_.]{1,64}}';
				$type = '{type:[a-z][a-z0-9-]{0,63}}';

				$api->get('', [Controller\Index::class, 'index'], 'index');
				$api->get('/types', [Controller\Types::class, 'list'], 'types');
				$api->get("/types/{$type}", [Controller\Types::class, 'type'], 'type');
				$api->get('/nodes', [Controller\Nodes::class, 'list'], 'nodes');
				$api->post('/nodes/query', [Controller\Nodes::class, 'query'], 'nodes.query');
				$api->post('/nodes', [Controller\Nodes::class, 'create'], 'nodes.create');
				$api->get("/nodes/{$uid}", [Controller\Nodes::class, 'get'], 'node');
				$api->put("/nodes/{$uid}", [Controller\Nodes::class, 'put'], 'node.put');
				$api->patch("/nodes/{$uid}", [Controller\Nodes::class, 'patch'], 'node.patch');
				$api->delete("/nodes/{$uid}", [Controller\Nodes::class, 'delete'], 'node.delete');
				$api->get('/search', [Controller\Nodes::class, 'search'], 'search');
				$api->get('/route', [Controller\Route::class, 'route'], 'route');
				$api->get('/menus', [Controller\Menus::class, 'list'], 'menus');
				$api->get('/menus/{menu:[a-z0-9-]{1,32}}', [Controller\Menus::class, 'menu'], 'menu');
				$api->get('/assets', [Controller\Assets::class, 'list'], 'assets');
				$api->post('/assets', [Controller\Assets::class, 'upload'], 'assets.upload');
				$api->get("/assets/{$uid}", [Controller\Assets::class, 'get'], 'asset');

				// Last, so a path the API does not know answers as a problem
				// instead of reaching the page catchall.
				$api->addRoute(Route::map(
					['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
					'/...slug',
					[Controller\Index::class, 'missing'],
					'missing',
				));
			},
			'cms.api.',
		);
	}
}
