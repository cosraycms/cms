<?php

declare(strict_types=1);

namespace Cosray\Tests\Integration;

use Celema\Container\Container;
use Celema\Core\Request;
use Cosray\Bootstrap;
use Cosray\Cms;
use Cosray\Context;
use Cosray\Controller\Panel\Reference as ReferenceController;
use Cosray\Field\Services;
use Cosray\Locales;
use Cosray\Tests\Fixtures\Node\TestNodeWithReference;
use Cosray\Tests\IntegrationTestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class ReferenceSearchTest extends IntegrationTestCase
{
	public function container(): Container
	{
		$container = parent::container();
		$container->tag(Bootstrap::NODE_TAG)->add('test-reference', TestNodeWithReference::class);

		return $container;
	}

	protected function setUp(): void
	{
		parent::setUp();

		$article = $this->typeId('test-article');
		$page = $this->typeId('test-page');

		$this->createTestNode(['uid' => 'ref-alpha', 'type' => $article, 'published' => true]);
		$this->createTestNode(['uid' => 'ref-beta', 'type' => $article, 'published' => false]);
		$this->createTestNode(['uid' => 'ref-gamma', 'type' => $article, 'published' => true]);
		$this->createTestNode(['uid' => 'ref-page', 'type' => $page, 'published' => true]);

		$this->setTitle('ref-alpha', ['en' => 'Alpha Article']);
		$this->setTitle('ref-beta', ['en' => 'Beta Article']);
		$this->setTitle('ref-gamma', ['en' => 'Gamma Article']);
		$this->setTitle('ref-page', ['en' => 'Some Page']);

		$this->db()->execute(
			"UPDATE cms.nodes SET deleted = now() WHERE uid = 'ref-gamma'",
		)->run();
	}

	public function testTypeConstraintSkipsDeletedKeepsUnpublished(): void
	{
		$result = $this->search(['type' => 'test-reference', 'field' => 'related']);
		$uids = $this->uids($result);

		$this->assertTrue($result['ok']);
		$this->assertContains('ref-alpha', $uids, 'published target is pickable');
		$this->assertContains('ref-beta', $uids, 'unpublished target is still pickable');
		$this->assertNotContains('ref-gamma', $uids, 'deleted target is excluded');
		$this->assertNotContains('ref-page', $uids, 'off-type node is excluded by #[Pick] types');

		foreach ($result['nodes'] as $node) {
			$this->assertSame('test-article', $node['type']);
		}
	}

	public function testExcludesTheEditedNodeItself(): void
	{
		// The `author` field has no #[Targets], so any non-deleted node is pickable.
		$result = $this->search(['type' => 'test-reference', 'field' => 'author', 'node' => 'ref-alpha']);
		$uids = $this->uids($result);

		$this->assertNotContains('ref-alpha', $uids, 'self-reference is excluded');
		$this->assertContains('ref-beta', $uids);
		$this->assertContains('ref-page', $uids, 'an unconstrained field picks any type');
	}

	public function testSearchMatchesTheMaterializedTitleColumn(): void
	{
		$result = $this->search(['type' => 'test-reference', 'field' => 'author', 'q' => 'Alpha']);
		$uids = $this->uids($result);

		$this->assertContains('ref-alpha', $uids);
		$this->assertNotContains('ref-beta', $uids);
		$this->assertNotContains('ref-page', $uids);
	}

	public function testPublishedGateExcludesDrafts(): void
	{
		// The `live` field is #[Pick(published: true)].
		$result = $this->search(['type' => 'test-reference', 'field' => 'live']);
		$uids = $this->uids($result);

		$this->assertContains('ref-alpha', $uids, 'published node is pickable');
		$this->assertContains('ref-page', $uids, 'published node of any type is pickable');
		$this->assertNotContains('ref-beta', $uids, 'the publication gate drops the draft');
	}

	public function testWhereClauseConstrainsByType(): void
	{
		// The `wherePick` field constrains type through the finder DSL.
		$result = $this->search(['type' => 'test-reference', 'field' => 'wherePick']);
		$uids = $this->uids($result);

		$this->assertContains('ref-alpha', $uids);
		$this->assertContains('ref-beta', $uids);
		$this->assertNotContains('ref-page', $uids, 'the where clause excludes the other type');
	}

	public function testUnknownTypeOrNonReferenceFieldYieldsEmpty(): void
	{
		$unknown = $this->search(['type' => 'no-such-type', 'field' => 'related']);
		$this->assertTrue($unknown['ok']);
		$this->assertSame([], $unknown['nodes']);

		// `title` exists on the node but is a Text field, not a Reference.
		$this->assertSame([], $this->search(['type' => 'test-reference', 'field' => 'title'])['nodes']);
	}

	public function testLabelsResolveChosenUidsInOrderAndSkipDeleted(): void
	{
		$result = $this->call('labels', ['uids' => 'ref-page,ref-gamma,ref-alpha']);
		$uids = $this->uids($result);

		// Requested order is preserved and the deleted target is dropped.
		$this->assertSame(['ref-page', 'ref-alpha'], $uids);
		$this->assertSame('test-article', $result['nodes'][1]['type']);
		$this->assertArrayHasKey('title', $result['nodes'][0]);
	}

	public function testLabelsWithoutUidsIsEmpty(): void
	{
		$result = $this->call('labels', ['uids' => '']);

		$this->assertTrue($result['ok']);
		$this->assertSame([], $result['nodes']);
	}

	private function search(array $params): array
	{
		return $this->call('search', $params);
	}

	private function call(string $method, array $params): array
	{
		$_GET = [];
		$this->set('GET', $params);

		$locales = new Locales();
		$locales->add('en', title: 'English');
		$psr = $this
			->psrRequest()
			->withAttribute('locales', $locales)
			->withAttribute('locale', $locales->get('en'))
			->withAttribute('defaultLocale', $locales->getDefault());
		$request = new Request($psr);

		$context = new Context(
			$this->db(),
			$request,
			$this->config(),
			$this->container(),
			$this->factory(),
		);
		$cms = new Cms($context, Services::withDefaults());
		$controller = new ReferenceController($this->config(), $this->container(), $request);
		$response = $controller->{$method}($cms, $this->factory());

		return json_decode((string) $response->getBody(), true);
	}

	/** @return list<string> */
	private function uids(array $result): array
	{
		return array_column($result['nodes'], 'uid');
	}

	private function typeId(string $handle): int
	{
		// The type row may already exist in the shared test schema; upsert
		// so we get its id either way (the transaction rolls back after).
		return (int) $this->db()->execute(
			'INSERT INTO cms.types (handle) VALUES (:handle)
			ON CONFLICT (handle) DO UPDATE SET handle = EXCLUDED.handle
			RETURNING type',
			['handle' => $handle],
		)->one()['type'];
	}

	/** @param array<string, string> $title */
	private function setTitle(string $uid, array $title): void
	{
		$this->db()->execute(
			'UPDATE cms.nodes SET title = :title::jsonb WHERE uid = :uid',
			['uid' => $uid, 'title' => json_encode($title)],
		)->run();
	}
}
