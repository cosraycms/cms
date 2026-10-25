<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Celema\Quma\Database;
use Cosray\Field\Text;
use Cosray\Tests\End2EndTestCase;
use Psr\Http\Message\ResponseInterface;

/**
 * @internal
 *
 * @coversNothing
 */
final class ApiWriteTest extends End2EndTestCase
{
	protected function setUp(): void
	{
		parent::setUp();

		$this->app = $this->createApp(['api.path' => '/api']);
		$this->loadFixtures('basic-types');
	}

	public function testCreatingANodeGeneratesItsUid(): void
	{
		$this->authenticateAs('editor');

		$response = $this->send('POST', '/api/nodes', [
			'type' => 'test-document',
			'published' => true,
			'content' => ['title' => ['value' => ['zxx' => 'Created document']]],
		]);

		$node = $this->json($response, 201);
		$this->assertSame('/api/nodes/' . $node['uid'], $response->getHeaderLine('Location'));
		$this->assertSame(
			$response->getHeaderLine('ETag'),
			$this->makeRequest('GET', '/api/nodes/' . $node['uid'])->getHeaderLine('ETag'),
		);
		$this->assertSame('Created document', $node['fields']['title']);
		$this->assertSame('auto-generated-id', $node['fields']['internalId']);
		$this->assertTrue($node['published']);
	}

	public function testCreatingNeedsAKnownTypeAndThePermission(): void
	{
		$body = ['type' => 'test-document', 'content' => ['title' => ['value' => ['zxx' => 'Nope']]]];

		$this->problem($this->send('POST', '/api/nodes', $body), 403);

		$this->authenticateAs('editor');
		$this->problem($this->send('POST', '/api/nodes', ['content' => []]), 400);
		$this->problem($this->send('POST', '/api/nodes', ['type' => 'nope']), 400);
		$this->problem($this->send('POST', '/api/nodes', ['type' => 'test-document', 'uid' => 'no spaces']), 400);
	}

	public function testInvalidContentAnswersWithItsIssues(): void
	{
		$this->authenticateAs('editor');

		$tooShort = $this->problem($this->send('POST', '/api/nodes', [
			'type' => 'test-document',
			'content' => ['title' => ['value' => ['zxx' => 'ab']]],
		]), 422);
		$this->assertSame(['content', 'title', 'value', 'zxx'], $tooShort['errors'][0]['path']);

		$unknown = $this->problem($this->send('POST', '/api/nodes', [
			'type' => 'test-document',
			'content' => ['titel' => ['value' => ['zxx' => 'Typo']]],
		]), 422);
		$this->assertSame(['content', 'titel'], $unknown['errors'][0]['path']);

		$this->problem($this->send('POST', '/api/nodes', [
			'type' => 'test-document',
			'content' => ['title' => 'not an object'],
		]), 422);
		$this->problem($this->send('POST', '/api/nodes', ['type' => 'test-document', 'published' => 'yes']), 422);
		$this->problem($this->send('POST', '/api/nodes', ['type' => 'test-document', 'colour' => 'red']), 400);
	}

	public function testPutCreatesUnderTheClientsUidAndThenReplacesTheContent(): void
	{
		$this->authenticateAs('editor');

		$created = $this->json($this->send('PUT', '/api/nodes/client-chosen', [
			'type' => 'test-document',
			'published' => true,
			'content' => [
				'title' => ['value' => ['zxx' => 'First version']],
				'intro' => ['value' => ['en' => 'An intro']],
			],
		]), 201);
		$this->assertSame('client-chosen', $created['uid']);

		$replaced = $this->json($this->send('PUT', '/api/nodes/client-chosen', [
			'content' => ['title' => ['value' => ['zxx' => 'Second version']]],
		]));

		$this->assertSame('Second version', $replaced['fields']['title']);
		$this->assertSame('', $replaced['fields']['intro']);
		$this->assertTrue($replaced['published']);
	}

	public function testConditionalRequestsGuardAgainstLostUpdates(): void
	{
		$this->authenticateAs('editor');
		$this->page('guarded', ['en' => 'Guarded']);
		$etag = $this->makeRequest('GET', '/api/nodes/guarded')->getHeaderLine('ETag');
		$body = ['content' => ['title' => ['value' => ['en' => 'Changed']]]];

		$this->problem($this->send('PUT', '/api/nodes/guarded', $body, ['If-None-Match' => '*']), 412);
		$this->problem($this->send('PUT', '/api/nodes/guarded', $body, ['If-Match' => '"stale"']), 412);
		$this->problem($this->send('PUT', '/api/nodes/not-there', ['type' => 'test-page'], ['If-Match' => $etag]), 412);

		$changed = $this->send('PATCH', '/api/nodes/guarded', $body, ['If-Match' => $etag]);
		$this->json($changed);
		$this->assertNotSame($etag, $changed->getHeaderLine('ETag'));
		$this->problem($this->send('PATCH', '/api/nodes/guarded', $body, ['If-Match' => $etag]), 412);
	}

	public function testAWriteCannotChangeTheTypeOrTheUid(): void
	{
		$this->authenticateAs('editor');
		$this->page('fixed', ['en' => 'Fixed']);

		$this->problem($this->send('PUT', '/api/nodes/fixed', ['type' => 'test-document']), 409);
		$this->problem($this->send('PUT', '/api/nodes/fixed', ['uid' => 'other']), 400);
		$this->problem($this->send('PATCH', '/api/nodes/fixed', ['uid' => 'other']), 422);
	}

	public function testPatchMergesContentAndNodeMembers(): void
	{
		$this->authenticateAs('editor');
		$this->page('patched', ['en' => 'English', 'de' => 'Deutsch']);

		$node = $this->json($this->send(
			'PATCH',
			'/api/nodes/patched',
			[
				'hidden' => true,
				'content' => ['title' => ['value' => ['de' => 'Neu']]],
			],
			contentType: 'application/merge-patch+json',
		));

		$this->assertTrue($node['hidden']);
		$this->assertTrue($node['published']);

		$source = $this->json($this->makeRequest('GET', '/api/nodes/patched', ['query' => ['view' => 'source']]));
		$this->assertEquals(['en' => 'English', 'de' => 'Neu'], $source['content']['title']['value']);
		$this->problem($this->send('PATCH', '/api/nodes/patched', [], contentType: 'text/plain'), 415);
	}

	public function testASourceViewWritesBackUnchanged(): void
	{
		$this->authenticateAs('editor');
		$this->page('round-trip', ['en' => 'Round trip', 'de' => 'Rundreise']);
		$source = $this->json($this->makeRequest('GET', '/api/nodes/round-trip', ['query' => ['view' => 'source']]));

		$written = $this->json($this->send('PUT', '/api/nodes/round-trip', $source), 200);

		$this->assertSame('Round trip', $written['fields']['title']);
		$this->assertSame($source['paths'], $written['paths']);
	}

	public function testPatchKeepsAnEditorsWorkingCopyInStep(): void
	{
		$this->authenticateAs('editor');
		$nodeId = $this->page('drafted', ['en' => 'Live', 'de' => 'Live DE']);
		$this->draft($nodeId, ['en' => 'Draft', 'de' => 'Draft DE']);

		$this->json($this->send('PATCH', '/api/nodes/drafted', [
			'content' => ['title' => ['value' => ['de' => 'API']]],
		]));

		$live = $this->json($this->makeRequest('GET', '/api/nodes/drafted', ['query' => ['view' => 'source']]));
		$this->assertEquals(['en' => 'Live', 'de' => 'API'], $live['content']['title']['value']);
		$this->assertEquals(['en' => 'Draft', 'de' => 'API'], $this->draftTitle($nodeId));
		$this->problem($this->send('PUT', '/api/nodes/drafted', ['content' => []]), 409);
	}

	public function testUnpublishingFoldsTheWorkingCopyIntoTheNode(): void
	{
		$this->authenticateAs('editor');
		$nodeId = $this->page('folded', ['en' => 'Live']);
		$this->draft($nodeId, ['en' => 'Draft']);

		$node = $this->json($this->send('PATCH', '/api/nodes/folded', ['published' => false]));

		$this->assertFalse($node['published']);
		$this->assertSame('Draft', $node['fields']['title']);
		$this->assertNull($this->draftTitle($nodeId));
	}

	public function testDeletingSoftDeletesTheNodeAndOptionallyItsChildren(): void
	{
		$this->authenticateAs('editor');
		$parentId = $this->page('parent-page', ['en' => 'Parent']);
		$this->page('child-page', ['en' => 'Child'], parent: $parentId);
		$this->page('lonely', ['en' => 'Lonely']);

		$this->assertSame(204, $this->makeRequest('DELETE', '/api/nodes/lonely')->getStatusCode());
		$this->problem($this->makeRequest('GET', '/api/nodes/lonely'), 404);
		$this->assertNotNull($this->json($this->makeRequest('GET', '/api/nodes/lonely', ['query' => [
			'deleted' => 'true',
		]]))['deleted']);
		$this->problem($this->send('PATCH', '/api/nodes/lonely', ['hidden' => true]), 404);

		$this->problem($this->makeRequest('DELETE', '/api/nodes/parent-page'), 409);
		$this->assertSame(204, $this->makeRequest('DELETE', '/api/nodes/parent-page', ['query' => [
			'children' => 'true',
		]])->getStatusCode());
		$this->problem($this->makeRequest('GET', '/api/nodes/child-page'), 404);
	}

	public function testWritesNeedTheirPermissionsAndAreAttributedToTheTokensUser(): void
	{
		$this->page('anonymous-target', ['en' => 'Public']);

		$this->problem($this->send('PATCH', '/api/nodes/anonymous-target', ['hidden' => true]), 403);
		$this->problem($this->makeRequest('DELETE', '/api/nodes/anonymous-target'), 403);

		$this->authenticateAs('editor');
		$this->json($this->send('PATCH', '/api/nodes/anonymous-target', ['hidden' => true]));
		$editor = $this->db()->execute(
			"SELECT u.uid FROM cms.nodes n JOIN cms.users u ON u.usr = n.editor WHERE n.uid = 'anonymous-target'",
		)->one()['uid'];
		$this->assertStringStartsWith('test-auth-', $editor);
	}

	public function testALockedNodeRefusesWrites(): void
	{
		$this->authenticateAs('editor');
		$this->page('locked-page', ['en' => 'Locked']);
		$this->db()->execute("UPDATE cms.nodes SET locked = true WHERE uid = 'locked-page'")->run();

		$this->problem($this->send('PATCH', '/api/nodes/locked-page', ['hidden' => true]), 409);
		$this->problem($this->makeRequest('DELETE', '/api/nodes/locked-page'), 409);
		$this->assertNull(
			$this->db()->execute(
				"SELECT deleted FROM cms.nodes WHERE uid = 'locked-page'",
			)->one()['deleted'],
		);
	}

	public function testAWriteWaitsWhileAnotherSessionHoldsTheNode(): void
	{
		$this->authenticateAs('editor');
		$this->page('contested', ['en' => 'Contested']);
		$other = new Database($this->conn());
		$other->begin();
		$other->nodes->lock(['uid' => 'contested'])->run();
		// Waiting would block the single-threaded test; a timeout shows it.
		$this->db()->execute("SET LOCAL lock_timeout = '100ms'")->run();

		try {
			$response = $this->send('PATCH', '/api/nodes/contested', ['hidden' => true]);
		} finally {
			$other->rollback();
		}

		$this->assertGreaterThanOrEqual(500, $response->getStatusCode());
		$this->assertFalse(
			(bool) $this->db()->execute(
				"SELECT hidden FROM cms.nodes WHERE uid = 'contested'",
			)->one()['hidden'],
		);
	}

	public function testDeletingASubtreeStopsAtALockedDescendant(): void
	{
		$this->authenticateAs('editor');
		$parent = $this->page('open-parent', ['en' => 'Parent']);
		$this->page('locked-child', ['en' => 'Child'], $parent);
		$this->db()->execute("UPDATE cms.nodes SET locked = true WHERE uid = 'locked-child'")->run();

		$this->problem($this->makeRequest('DELETE', '/api/nodes/open-parent', ['query' => [
			'children' => 'true',
		]]), 409);
		$this->assertSame(
			0,
			(int) $this->db()->execute(
				"SELECT count(*) AS deleted FROM cms.nodes WHERE uid IN ('open-parent', 'locked-child') AND deleted IS NOT NULL",
			)->one()['deleted'],
		);
	}

	/** @param array<string, string> $title */
	private function page(string $uid, array $title, ?int $parent = null): int
	{
		$type = $this->db()->execute("SELECT type FROM cms.types WHERE handle = 'test-page'")->one();
		$nodeId = $this->createTestNode([
			'uid' => $uid,
			'type' => (int) $type['type'],
			'parent' => $parent,
			'content' => ['title' => ['type' => Text::class, 'value' => $title]],
			'title' => $title,
		]);
		$this->createTestPath($nodeId, "/test/{$uid}");

		return $nodeId;
	}

	/** @param array<string, string> $title */
	private function draft(int $nodeId, array $title): void
	{
		$this->db()->execute(
			'INSERT INTO cms.drafts (node, editor, content) VALUES (:node, 1, :content::jsonb)',
			['node' => $nodeId, 'content' => json_encode(['title' => ['type' => Text::class, 'value' => $title]])],
		)->run();
	}

	/** @return ?array<string, string> */
	private function draftTitle(int $nodeId): ?array
	{
		$row = $this->db()->execute('SELECT content FROM cms.drafts WHERE node = :node', ['node' => $nodeId])->first();

		return $row === null ? null : json_decode($row['content'], true)['title']['value'];
	}

	/**
	 * @param array<array-key, mixed> $body
	 * @param array<string, string> $headers
	 */
	private function send(
		string $method,
		string $uri,
		array $body,
		array $headers = [],
		string $contentType = 'application/json',
	): ResponseInterface {
		return $this->makeRequest($method, $uri, [
			'body' => json_encode($body === [] ? new \stdClass() : $body),
			'headers' => ['Content-Type' => $contentType, ...$headers],
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
}
