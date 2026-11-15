<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Cosray\Bootstrap;
use Cosray\Config;
use Cosray\Field\Text;
use Cosray\Tests\End2EndTestCase;
use Override;
use Psr\Http\Message\ResponseInterface;

/**
 * Records are private: they have no publication, so a token that may only
 * read live nodes never learns of them.
 *
 * @internal
 *
 * @coversNothing
 */
final class ApiRecordsTest extends End2EndTestCase
{
	protected function setUp(): void
	{
		parent::setUp();

		$this->app = $this->createApp(['api.path' => '/api']);
		$this->loadFixtures('basic-types');
		$type = $this->createTestType('test-submission');
		// A stale publication row, as a type turned record before the sync leaves it.
		$this->createTestNode([
			'uid' => 'submitted',
			'type' => $type,
			'published' => true,
			'content' => ['title' => ['type' => Text::class, 'value' => ['zxx' => 'Submitted']]],
		]);
		$this->createTestNode([
			'uid' => 'live-document',
			'type' => $this->type('test-document'),
			'published' => true,
			'content' => ['title' => ['type' => Text::class, 'value' => ['zxx' => 'Live']]],
		]);
	}

	#[Override]
	protected function createBootstrap(Config $config): Bootstrap
	{
		$bootstrap = parent::createBootstrap($config);
		$bootstrap->policy()->role('reader', 'Reader');
		$bootstrap->policy()->allow('role:reader', 'read-nodes');

		return $bootstrap;
	}

	public function testAReadOnlyTokenNeitherListsNorSyncsNorReadsRecords(): void
	{
		$this->authenticateAs('reader');

		$listed = $this->json($this->makeRequest('GET', '/api/nodes', ['query' => ['published' => 'any']]));
		$synced = $this->json($this->makeRequest('GET', '/api/nodes', ['query' => [
			'changedSince' => '2000-01-01T00:00:00Z',
		]]));

		$this->assertSame(['live-document'], array_column($listed['items'], 'uid'));
		$this->assertSame(['live-document'], array_column($synced['items'], 'uid'));
		$this->assertSame([], $synced['removed']);
		$this->assertSame(404, $this->makeRequest('GET', '/api/nodes/submitted')->getStatusCode());
	}

	public function testARecordHasNoPublicationMembers(): void
	{
		$this->authenticateAs('editor');
		$this->db()->execute('DELETE FROM cms.publications WHERE node = (SELECT node FROM cms.nodes WHERE uid = :uid)', [
			'uid' => 'submitted',
		])->run();

		$record = $this->json($this->makeRequest('GET', '/api/nodes/submitted'));
		$unpublished = $this->json($this->makeRequest('GET', '/api/nodes', ['query' => [
			'type' => 'test-submission',
			'published' => 'false',
		]]));

		$this->assertArrayNotHasKey('published', $record);
		$this->assertArrayNotHasKey('hidden', $record);
		$this->assertSame(['submitted'], array_column($unpublished['items'], 'uid'));
	}

	public function testWritesThatPublishARecordAreRefused(): void
	{
		$this->authenticateAs('editor');

		$this->problem($this->send('POST', '/api/nodes', ['type' => 'test-submission', 'published' => true]), 422);
		$this->problem($this->send('PATCH', '/api/nodes/submitted', ['hidden' => true]), 422);
		$this->problem($this->send('PATCH', '/api/nodes/submitted', ['published' => false]), 422);
		$this->json($this->send('PATCH', '/api/nodes/submitted', [
			'content' => ['title' => ['value' => ['zxx' => 'Corrected']]],
		]));
	}

	private function type(string $handle): int
	{
		return (int) $this->db()->execute('SELECT type FROM cms.types WHERE handle = :handle', [
			'handle' => $handle,
		])->one()['type'];
	}

	/** @param array<string, mixed> $body */
	private function send(string $method, string $uri, array $body): ResponseInterface
	{
		return $this->makeRequest($method, $uri, [
			'body' => json_encode($body),
			'headers' => ['Content-Type' => 'application/json'],
		]);
	}

	/** @return array<string, mixed> */
	private function json(ResponseInterface $response): array
	{
		$this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());

		return json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
	}

	private function problem(ResponseInterface $response, int $status): void
	{
		$this->assertSame($status, $response->getStatusCode(), (string) $response->getBody());
	}
}
