<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Cosray\Field\Text;
use Cosray\Tests\End2EndTestCase;
use Psr\Http\Message\ResponseInterface;

/**
 * @internal
 *
 * @coversNothing
 */
final class ApiReadTest extends End2EndTestCase
{
	protected function setUp(): void
	{
		parent::setUp();

		$this->app = $this->createApp(['api.path' => '/api']);
		$this->loadFixtures('basic-types');
	}

	public function testTheApiAnswersOnlyWhenItHasAPath(): void
	{
		$this->app = $this->createApp();

		$response = $this->makeRequest('GET', '/api/nodes');

		$this->assertSame(404, $response->getStatusCode());
		$this->assertStringNotContainsString('problem+json', $response->getHeaderLine('Content-Type'));
	}

	public function testTheIndexDescribesLocalesAndTheTypesAnonymousClientsRead(): void
	{
		$index = $this->json($this->makeRequest('GET', '/api'));

		$this->assertSame('en', $index['defaultLocale']);
		$this->assertSame(['en'], array_column($index['locales'], 'fallbacks', 'id')['de']);
		$handles = array_column($index['types'], 'handle');
		$this->assertContains('test-page', $handles);
		$this->assertNotContains('test-document', $handles);
		$this->assertNull($index['user']);
	}

	public function testTheIndexNamesTheUserATokenActsAs(): void
	{
		$this->authenticateAs('editor');

		$index = $this->json($this->makeRequest('GET', '/api'));

		$this->assertNotNull($index['user']);
		$this->assertContains('test-document', array_column($index['types'], 'handle'));
	}

	public function testATokenThatDoesNotAuthenticateIsRefused(): void
	{
		foreach (['Bearer unknown-token', 'Basic dXNlcjpwYXNz'] as $header) {
			$response = $this->makeRequest('GET', '/api/nodes', ['headers' => ['Authorization' => $header]]);

			$problem = $this->problem($response, 401);
			$this->assertSame('Bearer realm="api"', $response->getHeaderLine('WWW-Authenticate'));
			$this->assertArrayHasKey('detail', $problem);
		}
	}

	public function testAnyoneReadsAPublishedPage(): void
	{
		$this->page('api-page', ['en' => 'About us', 'de' => 'Über uns'], path: '/about');

		$node = $this->json($this->makeRequest('GET', '/api/nodes/api-page'));

		$this->assertSame('api-page', $node['uid']);
		$this->assertSame('test-page', $node['type']);
		$this->assertSame('en', $node['locale']);
		$this->assertSame('About us', $node['title']);
		$this->assertSame('/about', $node['path']);
		$this->assertSame(['title' => 'About us'], $node['fields']);
		$this->assertTrue($node['published']);
		$this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $node['changed']);
		$this->assertArrayNotHasKey('content', $node);
	}

	public function testValuesResolveForTheRequestedLocaleAndItsFallbacks(): void
	{
		$this->page('api-locale', ['en' => 'Only English']);

		$node = $this->json($this->makeRequest('GET', '/api/nodes/api-locale', ['query' => ['locale' => 'de']]));

		$this->assertSame('de', $node['locale']);
		$this->assertSame('Only English', $node['fields']['title']);
	}

	public function testAnUnknownLocaleIsABadRequest(): void
	{
		$this->page('api-locale-bad', ['en' => 'Title']);

		$response = $this->makeRequest('GET', '/api/nodes/api-locale-bad', ['query' => ['locale' => 'xx']]);

		$this->problem($response, 400);
	}

	public function testTheSourceViewCarriesEveryLocale(): void
	{
		$this->page('api-source', ['en' => 'English', 'de' => 'Deutsch']);

		$node = $this->json($this->makeRequest('GET', '/api/nodes/api-source', ['query' => ['view' => 'source']]));

		$this->assertEquals(['en' => 'English', 'de' => 'Deutsch'], $node['content']['title']['value']);
		$this->assertArrayNotHasKey('type', $node['content']['title']);
		$this->assertArrayNotHasKey('fields', $node);
	}

	public function testUnpublishedNodesAndRoutelessTypesNeedAToken(): void
	{
		$this->page('api-draft', ['en' => 'Draft'], published: false);
		$this->node('api-doc', 'test-document', ['title' => ['type' => Text::class, 'value' => ['zxx' => 'Doc']]]);

		$this->problem($this->makeRequest('GET', '/api/nodes/api-draft'), 404);
		$this->problem($this->makeRequest('GET', '/api/nodes/api-doc'), 404);

		$this->authenticateAs('editor');

		$this->assertFalse($this->json($this->makeRequest('GET', '/api/nodes/api-draft'))['published']);
		$this->assertSame('test-document', $this->json($this->makeRequest('GET', '/api/nodes/api-doc'))['type']);
	}

	public function testListsAreScopedFilteredOrderedAndPaginated(): void
	{
		$this->page('api-list-b', ['en' => 'Bravo']);
		$this->page('api-list-a', ['en' => 'Alpha']);
		$this->page('api-list-c', ['en' => 'Charlie']);
		$this->page('api-list-hidden-draft', ['en' => 'Aaron'], published: false);

		$list = $this->json($this->makeRequest('GET', '/api/nodes', [
			'query' => ['type' => 'test-page', 'limit' => '2', 'published' => 'any', 'fields' => 'title'],
		]));

		$this->assertSame(3, $list['total']);
		$this->assertSame(['Alpha', 'Bravo'], array_column($list['items'], 'title'));
		$this->assertSame(2, $list['limit']);

		$descending = $this->json($this->makeRequest('GET', '/api/nodes', [
			'query' => ['type' => 'test-page', 'order' => '-title'],
		]));
		$this->assertSame(['Charlie', 'Bravo', 'Alpha'], array_column($descending['items'], 'title'));
	}

	public function testAQueryTakesUidListsAndStructuredFilters(): void
	{
		$this->authenticateAs('editor');
		$this->node('api-q-1', 'test-widget', ['title' => ['type' => Text::class, 'value' => ['zxx' => 'One']]]);
		$this->node('api-q-2', 'test-widget', ['title' => ['type' => Text::class, 'value' => ['zxx' => 'Two']]]);
		$this->node('api-q-3', 'test-widget', ['title' => ['type' => Text::class, 'value' => ['zxx' => 'Three']]]);

		$byUid = $this->json($this->makeRequest('POST', '/api/nodes/query', [
			'body' => ['uid' => ['api-q-1', 'api-q-3', 'missing']],
		]));
		$this->assertSame(['api-q-1', 'api-q-3'], array_column($byUid['items'], 'uid'));

		$filtered = $this->json($this->makeRequest('POST', '/api/nodes/query', [
			'body' => [
				'type' => ['test-widget'],
				'filter' => ['or' => [['title' => 'One'], ['title' => ['in' => ['Two', 'Nine']]]]],
				'order' => ['uid'],
			],
		]));
		$this->assertSame(['api-q-1', 'api-q-2'], array_column($filtered['items'], 'uid'));
	}

	public function testABadQueryIsABadRequest(): void
	{
		$this->authenticateAs('editor');

		$cases = [
			['GET', '/api/nodes', ['query' => ['filter' => '{"title": {"regex": "x"}}']]],
			['GET', '/api/nodes', ['query' => ['filter' => 'not json']]],
			['GET', '/api/nodes', ['query' => ['type' => 'nope']]],
			['GET', '/api/nodes', ['query' => ['order' => 'editor']]],
			['GET', '/api/nodes', ['query' => ['limit' => '0']]],
			['GET', '/api/nodes', ['query' => ['view' => 'raw']]],
			['POST', '/api/nodes/query', ['body' => '[1, 2]', 'headers' => ['Content-Type' => 'application/json']]],
			['POST', '/api/nodes/query', ['body' => '{', 'headers' => ['Content-Type' => 'application/json']]],
			['POST', '/api/nodes/query', ['body' => ['filter' => ['creator' => 'x']]]],
		];

		foreach ($cases as [$method, $uri, $options]) {
			$this->problem($this->makeRequest($method, $uri, $options), 400);
		}
	}

	public function testAnUnknownEndpointAnswersWithAProblem(): void
	{
		$this->problem($this->makeRequest('GET', '/api/nope'), 404);
		$this->problem($this->makeRequest('GET', '/api/nodes/unknown-uid'), 404);
	}

	public function testTypesListTheReadableTypesWithTheActionsAClientMayTake(): void
	{
		$types = $this->json($this->makeRequest('GET', '/api/types'))['items'];
		$page = array_column($types, null, 'handle')['test-page'];

		$this->assertSame('/test/{uid}', $page['route']);
		$this->assertSame(['read' => true, 'create' => false, 'change' => false, 'delete' => false], $page['actions']);
		$this->assertNotContains('test-document', array_column($types, 'handle'));
	}

	public function testATypeDescribesItsFieldsAndTheirDefaults(): void
	{
		$this->authenticateAs('editor');

		$type = $this->json($this->makeRequest('GET', '/api/types/test-document'));
		$fields = array_column($type['fields'], null, 'name');

		$this->assertSame('text', $fields['title']['type']);
		$this->assertTrue($fields['title']['required']);
		$this->assertSame('textarea', $fields['intro']['type']);
		$this->assertSame('auto-generated-id', $fields['internalId']['default']['value']['zxx']);
		$this->assertArrayNotHasKey('width', $fields['intro']);
		$this->problem($this->makeRequest('GET', '/api/types/nope'), 404);
	}

	public function testATypeDescribesTheRowsOfItsBlocksAndEntries(): void
	{
		$this->authenticateAs('editor');

		$entries = array_column(
			$this->json($this->makeRequest('GET', '/api/types/test-node-with-entries'))['fields'],
			null,
			'name',
		);
		$rows = array_column($entries['entries']['rows'], null, 'type');
		$this->assertSame(['test-entry', 'test-alternate-entry'], array_keys($rows));
		$this->assertContains('title', array_column($rows['test-entry']['fields'], 'name'));

		$media = array_column(
			$this->json($this->makeRequest('GET', '/api/types/test-media-document'))['fields'],
			null,
			'name',
		);
		$this->assertSame(12, $media['contentBlocks']['columns']);
		$this->assertContains('text', array_column($media['contentBlocks']['rows'], 'type'));
	}

	public function testListsSelectByHandleAndParent(): void
	{
		$parent = $this->node('api-parent', 'test-widget', [
			'title' => ['type' => Text::class, 'value' => ['zxx' => 'Parent']],
		]);
		$this->db()->execute(
			"INSERT INTO cms.node_handles (node, handle, creator, editor) VALUES (:node, 'the-parent', 1, 1)",
			['node' => $parent],
		)->run();
		$this->createTestNode([
			'uid' => 'api-child',
			'type' => (int) $this->db()->execute(
				"SELECT type FROM cms.types WHERE handle = 'test-widget'",
			)->one()['type'],
			'parent' => $parent,
			'content' => ['title' => ['type' => Text::class, 'value' => ['zxx' => 'Child']]],
		]);
		$this->authenticateAs('editor');

		$byHandle = $this->json($this->makeRequest('GET', '/api/nodes', ['query' => ['handle' => 'the-parent']]));
		$children = $this->json($this->makeRequest('GET', '/api/nodes', ['query' => ['parent' => 'api-parent']]));

		$this->assertSame(['api-parent'], array_column($byHandle['items'], 'uid'));
		$this->assertSame('the-parent', $byHandle['items'][0]['handle']);
		$this->assertSame(['api-child'], array_column($children['items'], 'uid'));
		$this->assertSame('api-parent', $children['items'][0]['parent']);
	}

	public function testATypedOrderThatDoesNotFitTheValuesIsABadRequest(): void
	{
		$this->authenticateAs('editor');
		$this->node('api-typed', 'test-widget', [
			'title' => ['type' => Text::class, 'value' => ['zxx' => 'Not a number']],
		]);

		$this->problem($this->makeRequest('GET', '/api/nodes', ['query' => [
			'type' => 'test-widget',
			'order' => 'title:number',
		]]), 400);
	}

	public function testTheApiFollowsTheUrlPrefix(): void
	{
		$this->app = $this->createApp(['api.path' => '/api', 'app.url_prefix' => '/cms']);
		$this->page('api-prefixed', ['en' => 'Prefixed'], path: '/prefixed');

		$node = $this->json($this->makeRequest('GET', '/cms/api/nodes/api-prefixed'));

		$this->assertSame('/prefixed', $node['path']);
	}

	protected function createBootstrap(\Cosray\Config $config): \Cosray\Bootstrap
	{
		$bootstrap = parent::createBootstrap($config);
		$bootstrap->node(\Cosray\Tests\Fixtures\Node\TestNodeWithEntries::class);

		return $bootstrap;
	}

	/**
	 * @param array<string, string> $title
	 */
	private function page(string $uid, array $title, bool $published = true, ?string $path = null): void
	{
		$nodeId = $this->node(
			$uid,
			'test-page',
			['title' => ['type' => Text::class, 'value' => $title]],
			$published,
		);
		$this->createTestPath($nodeId, $path ?? "/test/{$uid}");
	}

	/** @param array<string, mixed> $content */
	private function node(string $uid, string $type, array $content, bool $published = true): int
	{
		$row = $this->db()->execute('SELECT type FROM cms.types WHERE handle = :handle', ['handle' => $type])->one();

		return $this->createTestNode([
			'uid' => $uid,
			'type' => (int) $row['type'],
			'published' => $published,
			'content' => $content,
			'title' => array_map(static fn(mixed $value): mixed => $value, $content['title']['value'] ?? []),
		]);
	}

	/** @return array<string, mixed> */
	private function json(ResponseInterface $response): array
	{
		$this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
		$this->assertSame('application/json', $response->getHeaderLine('Content-Type'));

		return json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
	}

	/** @return array<string, mixed> */
	private function problem(ResponseInterface $response, int $status): array
	{
		$this->assertSame($status, $response->getStatusCode(), (string) $response->getBody());
		$this->assertSame('application/problem+json', $response->getHeaderLine('Content-Type'));
		$problem = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
		$this->assertSame($status, $problem['status']);

		return $problem;
	}
}
