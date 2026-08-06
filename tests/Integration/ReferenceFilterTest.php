<?php

declare(strict_types=1);

namespace Cosray\Tests\Integration;

use Celema\Container\Container;
use Cosray\Bootstrap;
use Cosray\References\Sync;
use Cosray\Tests\Fixtures\Node\TestNodeWithReference;
use Cosray\Tests\IntegrationTestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class ReferenceFilterTest extends IntegrationTestCase
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
		$owner = $this->typeId('test-reference');

		$this->createTestNode(['uid' => 'reffilter-alpha', 'type' => $article]);
		$this->createTestNode(['uid' => 'reffilter-beta', 'type' => $article]);

		$this->createOwner('reffilter-one', $owner, related: 'reffilter-alpha', author: 'reffilter-beta');
		$this->createOwner('reffilter-two', $owner, related: 'reffilter-beta', author: 'reffilter-beta');
		$this->createOwner('reffilter-none', $owner, related: null, author: null);
	}

	public function testFieldScopedFilterMatchesTheReferencingField(): void
	{
		$this->assertSame(
			['reffilter-one'],
			$this->find("references.related = 'reffilter-alpha'"),
		);
		$this->assertSame(
			['reffilter-one', 'reffilter-two'],
			$this->find("references.author = 'reffilter-beta'"),
		);
		// The uid sits in `author`, so the `related` question stays unanswered.
		$this->assertSame([], $this->find("references.related = 'reffilter-gamma'"));
	}

	public function testFieldScopedFilterAcceptsListsAndNegation(): void
	{
		$this->assertSame(
			['reffilter-one', 'reffilter-two'],
			$this->find("references.related @ ['reffilter-alpha', 'reffilter-beta']"),
		);
		$this->assertSame(
			['reffilter-none', 'reffilter-two'],
			$this->find("references.related != 'reffilter-alpha'"),
		);
	}

	public function testUnscopedFilterReadsTheReferenceIndex(): void
	{
		$sync = new Sync($this->db());
		$sync->replace('node', 'reffilter-one', [
			'assets' => [],
			'nodes' => ['reffilter-alpha', 'reffilter-beta'],
		]);
		$sync->replace('node', 'reffilter-two', ['assets' => [], 'nodes' => ['reffilter-beta']]);

		$this->assertSame(['reffilter-one'], $this->find("references = 'reffilter-alpha'"));
		$this->assertSame(
			['reffilter-one', 'reffilter-two'],
			$this->find("references = 'reffilter-beta'"),
		);
		$this->assertSame(['reffilter-none'], $this->find("references != 'reffilter-beta'"));
	}

	/** @return list<string> */
	private function find(string $query): array
	{
		$nodes = $this
			->createCms()
			->nodes
			->types('test-reference')
			->filter($query)
			->order('uid ASC');

		return array_map(
			static fn(object $node): string => $node->meta->uid,
			iterator_to_array($nodes),
		);
	}

	private function createOwner(string $uid, int $type, ?string $related, ?string $author): void
	{
		$content = [];

		foreach (['related' => $related, 'author' => $author] as $field => $target) {
			if ($target !== null) {
				$content[$field] = [
					'type' => 'Cosray\\Field\\Reference',
					'value' => ['zxx' => [['uid' => $target]]],
				];
			}
		}

		$this->createTestNode([
			'uid' => $uid,
			'type' => $type,
			'content' => json_encode($content, JSON_THROW_ON_ERROR),
		]);
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
}
