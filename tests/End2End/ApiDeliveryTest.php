<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Cosray\Bootstrap;
use Cosray\Config;
use Cosray\Field\Text;
use Cosray\Menus;
use Cosray\Tests\End2EndTestCase;
use Cosray\Tests\Fixtures\Node\FulltextPage;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UploadedFileInterface;

/**
 * @internal
 *
 * @coversNothing
 */
final class ApiDeliveryTest extends End2EndTestCase
{
	private const string PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

	private string $publicDir;

	protected function setUp(): void
	{
		parent::setUp();

		$this->publicDir = sys_get_temp_dir() . '/cosray-api-' . bin2hex(random_bytes(4));
		mkdir("{$this->publicDir}/assets", 0o755, true);
		$this->app = $this->createApp(['api.path' => '/api', 'path.public' => $this->publicDir]);
		$this->loadFixtures('basic-types');
	}

	protected function tearDown(): void
	{
		try {
			$this->removeDir($this->publicDir);
		} finally {
			parent::tearDown();
		}
	}

	public function testARouteResolvesToTheNodeInThePathsLocale(): void
	{
		$nodeId = $this->page('routed', ['en' => 'About', 'de' => 'Über'], '/about');
		$this->createTestPath($nodeId, '/ueber', 'de');

		$english = $this->json($this->makeRequest('GET', '/api/route', ['query' => ['path' => '/about']]));
		$german = $this->json($this->makeRequest('GET', '/api/route', ['query' => ['path' => '/ueber']]));

		$this->assertSame('routed', $english['node']['uid']);
		$this->assertSame('en', $english['node']['locale']);
		$this->assertSame('de', $german['node']['locale']);
		$this->assertSame('Über', $german['node']['fields']['title']);
	}

	public function testAnOldPathAnswersWithTheActivePath(): void
	{
		$nodeId = $this->page('moved', ['en' => 'Moved'], '/new-place');
		$this->db()->execute(
			"INSERT INTO cms.url_paths (node, path, locale, creator, editor, inactive) VALUES (:node, '/old-place', 'en', 1, 1, now())",
			['node' => $nodeId],
		)->run();

		$this->assertSame(
			['redirect' => '/new-place'],
			$this->json($this->makeRequest('GET', '/api/route', ['query' => ['path' => '/old-place']])),
		);
	}

	public function testARouteFollowsTheWebsiteNotTheTypePermissions(): void
	{
		$this->page('hidden-route', ['en' => 'Draft'], '/draft-route', published: false);
		$documentId = $this->createTestNode([
			'uid' => 'routeless',
			'type' => $this->type('test-document'),
			'content' => ['title' => ['type' => Text::class, 'value' => ['zxx' => 'Home']]],
		]);
		$this->createTestPath($documentId, '/home-document');

		$this->problem($this->makeRequest('GET', '/api/route', ['query' => ['path' => '/draft-route']]), 404);
		$this->problem($this->makeRequest('GET', '/api/route', ['query' => ['path' => '/nowhere']]), 404);
		$this->problem($this->makeRequest('GET', '/api/route', ['query' => ['path' => 'no-slash']]), 400);
		$this->assertSame(
			'routeless',
			$this->json($this->makeRequest('GET', '/api/route', ['query' => [
				'path' => '/home-document',
			]]))['node']['uid'],
		);
	}

	public function testMenusRenderAsTheWebsiteShowsThem(): void
	{
		$menus = new Menus($this->db());
		$menus->create('main', ['en' => 'Main navigation']);
		$top = $menus->add('main', ['type' => 'url', 'title' => ['en' => 'Shop'], 'path' => ['en' => '/shop']]);
		$menus->add(
			'main',
			['type' => 'url', 'title' => ['en' => 'Shoes'], 'path' => ['en' => '/shop/shoes']],
			parent: $top,
		);
		$menus->add(
			'main',
			['type' => 'url', 'title' => ['en' => 'Secret'], 'path' => ['en' => '/secret']],
			hidden: true,
		);

		$menu = $this->json($this->makeRequest('GET', '/api/menus/main'));

		$this->assertSame(['Shop'], array_column($menu['items'], 'title'));
		$this->assertSame('/shop', $menu['items'][0]['href']);
		$this->assertSame('Shoes', $menu['items'][0]['children'][0]['title']);
		$this->assertContains(
			['handle' => 'main', 'description' => 'Main navigation'],
			$this->json($this->makeRequest('GET', '/api/menus'))['items'],
		);
		$this->problem($this->makeRequest('GET', '/api/menus/nope'), 404);
	}

	public function testAssetsUploadListAndRead(): void
	{
		$this->problem($this->upload(), 403);
		$this->authenticateAs('editor');

		$created = $this->upload(['meta' => json_encode(['alt' => ['en' => 'A pixel']])]);
		$asset = $this->json($created, 201);
		$this->assertSame('/api/assets/' . $asset['uid'], $created->getHeaderLine('Location'));
		$this->assertSame('image', $asset['kind']);
		$this->assertSame('A pixel', $asset['alt']);
		$this->assertStringEndsWith('-thumb.png', $asset['sizes']['thumb']);
		$this->assertSame($asset['uid'], $this->json($this->upload(), 200)['uid']);

		$list = $this->json($this->makeRequest('GET', '/api/assets', ['query' => ['kind' => 'image', 'q' => 'pixel']]));
		$this->assertSame([$asset['uid']], array_column($list['items'], 'uid'));

		$this->defaultAuthToken = null;
		$this->assertSame($asset['uid'], $this->json($this->makeRequest('GET', '/api/assets/' . $asset['uid']))['uid']);
		$this->problem($this->makeRequest('GET', '/api/assets'), 403);
		$this->problem($this->makeRequest('GET', '/api/assets/unknown'), 404);
	}

	public function testAnUploadNeedsAnAcceptableFile(): void
	{
		$this->authenticateAs('editor');

		$this->problem($this->makeRequest('POST', '/api/assets'), 400);
		$this->problem($this->upload(['type' => 'audio']), 400);
		$this->problem($this->upload(['type' => 'video']), 422);
	}

	public function testSearchFindsWordsAndOrdersByRelevance(): void
	{
		$this->authenticateAs('editor');

		foreach ([
			'first' => 'Apple pie recipe',
			'second' => 'Banana bread',
			'third' => 'Apple cider',
		] as $uid => $body) {
			$this->json($this->send('PUT', "/api/nodes/{$uid}", [
				'type' => 'fulltext-page',
				'published' => true,
				'content' => [
					'name' => ['value' => ['en' => ucfirst($uid)]],
					'body' => ['value' => ['en' => $body]],
				],
			]), 201);
		}

		$this->defaultAuthToken = null;
		$found = $this->json($this->makeRequest('GET', '/api/search', ['query' => ['q' => 'apple']]));

		$this->assertSame(2, $found['total']);
		$this->assertEqualsCanonicalizing(['first', 'third'], array_column($found['items'], 'uid'));
		$this->assertStringContainsString('Apple', $found['items'][0]['search']['snippet']);
		$this->problem($this->makeRequest('GET', '/api/search'), 400);
	}

	public function testCorsAllowsTheConfiguredOrigins(): void
	{
		$this->app = $this->createApp(['api.path' => '/api', 'api.origins' => ['https://front.example']]);

		$preflight = $this->makeRequest('OPTIONS', '/api/nodes', ['headers' => [
			'Origin' => 'https://front.example',
			'Access-Control-Request-Method' => 'PATCH',
		]]);
		$read = $this->makeRequest('GET', '/api', ['headers' => ['Origin' => 'https://front.example']]);
		$foreign = $this->makeRequest('GET', '/api', ['headers' => ['Origin' => 'https://evil.example']]);

		$this->assertSame(204, $preflight->getStatusCode());
		$this->assertSame('https://front.example', $preflight->getHeaderLine('Access-Control-Allow-Origin'));
		$this->assertStringContainsString('PATCH', $preflight->getHeaderLine('Access-Control-Allow-Methods'));
		$this->assertStringContainsString('Authorization', $preflight->getHeaderLine('Access-Control-Allow-Headers'));
		$this->assertSame('https://front.example', $read->getHeaderLine('Access-Control-Allow-Origin'));
		$this->assertStringContainsString('ETag', $read->getHeaderLine('Access-Control-Expose-Headers'));
		$this->assertFalse($foreign->hasHeader('Access-Control-Allow-Origin'));
	}

	public function testSharedCachesKeepOnlyAnonymousReads(): void
	{
		$this->app = $this->createApp(['api.path' => '/api', 'api.max_age' => 60]);

		$this->assertSame('public, max-age=60', $this->makeRequest('GET', '/api')->getHeaderLine('Cache-Control'));
		$this->assertSame('private, no-store', $this->makeRequest('GET', '/api/nope')->getHeaderLine('Cache-Control'));

		$this->authenticateAs('editor');
		$this->assertSame('private, no-store', $this->makeRequest('GET', '/api')->getHeaderLine('Cache-Control'));

		$this->app = $this->createApp(['api.path' => '/api']);
		$this->defaultAuthToken = null;
		$this->assertSame('no-cache', $this->makeRequest('GET', '/api')->getHeaderLine('Cache-Control'));
	}

	public function testChangedSinceListsChangesAndWhatWasRemoved(): void
	{
		$this->page('unchanged', ['en' => 'Unchanged'], '/unchanged');
		$this->page('updated', ['en' => 'Updated'], '/updated');
		$this->page('withdrawn', ['en' => 'Withdrawn'], '/withdrawn');
		// The change trigger stamps every update; set the times without it.
		$this->db()->execute('ALTER TABLE cms.nodes DISABLE TRIGGER USER')->run();
		$this->db()->execute("UPDATE cms.nodes SET changed = '2026-01-01T00:00:00Z' WHERE uid = 'unchanged'")->run();
		$this->db()->execute("UPDATE cms.nodes SET changed = '2026-06-01T00:00:00Z' WHERE uid = 'updated'")->run();
		$this->db()->execute(
			"UPDATE cms.nodes SET changed = '2026-06-01T00:00:00Z', published = false WHERE uid = 'withdrawn'",
		)->run();
		$this->db()->execute('ALTER TABLE cms.nodes ENABLE TRIGGER USER')->run();

		$sync = $this->json($this->makeRequest('GET', '/api/nodes', ['query' => [
			'type' => 'test-page',
			'changedSince' => '2026-03-01T00:00:00Z',
		]]));

		$this->assertSame(['updated'], array_column($sync['items'], 'uid'));
		$this->assertSame(
			[['uid' => 'withdrawn', 'type' => 'test-page', 'changed' => '2026-06-01T00:00:00Z']],
			$sync['removed'],
		);
		$this->problem($this->makeRequest('GET', '/api/nodes', ['query' => ['changedSince' => 'yesterday']]), 400);
	}

	protected function createBootstrap(Config $config): Bootstrap
	{
		$bootstrap = parent::createBootstrap($config);
		$bootstrap->node(FulltextPage::class);

		return $bootstrap;
	}

	/** @param array<string, string> $title */
	private function page(string $uid, array $title, string $path, bool $published = true): int
	{
		$nodeId = $this->createTestNode([
			'uid' => $uid,
			'type' => $this->type('test-page'),
			'published' => $published,
			'content' => ['title' => ['type' => Text::class, 'value' => $title]],
			'title' => $title,
		]);
		$this->createTestPath($nodeId, $path);

		return $nodeId;
	}

	private function type(string $handle): int
	{
		return (int) $this->db()->execute('SELECT type FROM cms.types WHERE handle = :handle', [
			'handle' => $handle,
		])->one()['type'];
	}

	/** @param array<string, string> $fields */
	private function upload(array $fields = []): ResponseInterface
	{
		$png = (string) base64_decode(self::PNG, true);

		return $this->makeRequest('POST', '/api/assets', [
			'files' => ['file' => $this->file($png, 'a-pixel.png', 'image/png')],
			'form' => $fields,
		]);
	}

	private function file(string $contents, string $name, string $type): UploadedFileInterface
	{
		return $this->factory()->uploadedFile(
			$this->factory()->streamFactory()->createStream($contents),
			strlen($contents),
			UPLOAD_ERR_OK,
			$name,
			$type,
		);
	}

	/** @param array<array-key, mixed> $body */
	private function send(string $method, string $uri, array $body): ResponseInterface
	{
		return $this->makeRequest($method, $uri, [
			'body' => json_encode($body),
			'headers' => ['Content-Type' => 'application/json'],
		]);
	}

	/** @return array<string, mixed> */
	private function json(ResponseInterface $response, int $status = 200): array
	{
		$this->assertSame($status, $response->getStatusCode(), (string) $response->getBody());

		return json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
	}

	/** @return array<string, mixed> */
	private function problem(ResponseInterface $response, int $status): array
	{
		$this->assertSame($status, $response->getStatusCode(), (string) $response->getBody());
		$this->assertSame('application/problem+json', $response->getHeaderLine('Content-Type'));

		return json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
	}

	private function removeDir(string $dir): void
	{
		if (!is_dir($dir)) {
			return;
		}

		foreach (scandir($dir) ?: [] as $item) {
			if ($item === '.' || $item === '..') {
				continue;
			}

			$path = "{$dir}/{$item}";
			is_dir($path) && !is_link($path) ? $this->removeDir($path) : unlink($path);
		}

		rmdir($dir);
	}
}
