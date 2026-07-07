<?php

declare(strict_types=1);

namespace Cosray\Tests\Integration;

use Celemas\Core\Request;
use Cosray\Cms;
use Cosray\Context;
use Cosray\Controller\Panel\Reference as ReferenceController;
use Cosray\Field\Services;
use Cosray\Locales;
use Cosray\Tests\IntegrationTestCase;

/**
 * The unconstrained node search backing the richtext link picker: any
 * non-deleted node of any type is pickable, the current node excluded.
 *
 * @internal
 *
 * @coversNothing
 */
final class ReferenceLinkSearchTest extends IntegrationTestCase
{
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

	public function testReturnsEveryNonDeletedNodeAcrossTypes(): void
	{
		$result = $this->nodes([]);
		$uids = $this->uids($result);

		$this->assertTrue($result['ok']);
		$this->assertContains('ref-alpha', $uids, 'published node is pickable');
		$this->assertContains('ref-beta', $uids, 'unpublished node is still pickable');
		$this->assertContains('ref-page', $uids, 'a link accepts any node type');
		$this->assertNotContains('ref-gamma', $uids, 'deleted node is excluded');
	}

	public function testExcludesTheCurrentNode(): void
	{
		$result = $this->nodes(['node' => 'ref-alpha']);
		$uids = $this->uids($result);

		$this->assertNotContains('ref-alpha', $uids, 'the linking node cannot link to itself');
		$this->assertContains('ref-beta', $uids);
		$this->assertContains('ref-page', $uids);
	}

	public function testSearchMatchesTheMaterializedTitleColumn(): void
	{
		$result = $this->nodes(['q' => 'Alpha']);
		$uids = $this->uids($result);

		$this->assertContains('ref-alpha', $uids);
		$this->assertNotContains('ref-beta', $uids);
		$this->assertNotContains('ref-page', $uids);
	}

	public function testLimitSignalsMore(): void
	{
		$result = $this->nodes(['limit' => '1']);

		$this->assertCount(1, $result['nodes']);
		$this->assertTrue($result['more'], 'the extra row beyond the limit sets the more flag');
	}

	private function nodes(array $params): array
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

		return json_decode((string) $controller->nodes($cms, $this->factory())->getBody(), true);
	}

	/** @return list<string> */
	private function uids(array $result): array
	{
		return array_column($result['nodes'], 'uid');
	}

	private function typeId(string $handle): int
	{
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
