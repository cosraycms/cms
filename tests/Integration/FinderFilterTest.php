<?php

declare(strict_types=1);

namespace Cosray\Tests\Integration;

use Cosray\Exception\ParserException;
use Cosray\Field\Text;
use Cosray\Tests\IntegrationTestCase;

final class FinderFilterTest extends IntegrationTestCase
{
	protected function setUp(): void
	{
		parent::setUp();

		$this->loadFixtures('basic-types');
		$type = (int) $this->db()->execute("SELECT type FROM cms.types WHERE handle = 'test-widget'")->one()['type'];

		foreach ([['filter-a', 'Apple', 3], ['filter-b', 'banana', 7], ['filter-c', 'Cherry', null]] as [
			$uid,
			$title,
			$rank,
		]) {
			$content = ['title' => ['type' => Text::class, 'value' => ['zxx' => $title]]];

			if ($rank !== null) {
				$content['rank'] = ['type' => Text::class, 'value' => ['zxx' => $rank]];
			}

			$this->createTestNode(['uid' => $uid, 'type' => $type, 'content' => $content]);
		}
	}

	public function testOperatorsCompareContentFieldsAndBuiltins(): void
	{
		$this->assertMatches(['filter-a'], ['title' => 'Apple']);
		$this->assertMatches(['filter-b', 'filter-c'], ['title' => ['ne' => 'Apple']]);
		$this->assertMatches(['filter-b'], ['rank' => ['gt' => 5]]);
		$this->assertMatches(['filter-a'], ['rank' => ['gte' => 3, 'lt' => 7]]);
		$this->assertMatches(['filter-a', 'filter-c'], ['title' => ['in' => ['Apple', 'Cherry']]]);
		$this->assertMatches(['filter-b'], ['title' => ['nin' => ['Apple', 'Cherry']]]);
		$this->assertMatches(['filter-b'], ['title' => ['like' => 'ban%']]);
		$this->assertMatches(['filter-a', 'filter-b'], ['title' => ['ilike' => '%A%']]);
		$this->assertMatches(['filter-c'], ['rank' => ['exists' => false]]);
		$this->assertMatches(['filter-a', 'filter-b'], ['rank' => ['exists' => true]]);
		$this->assertMatches(['filter-a', 'filter-b', 'filter-c'], ['parent' => ['null' => true]]);
		$this->assertMatches(['filter-c'], ['uid' => 'filter-c']);
	}

	public function testFiltersCombineWithAndOrAndNot(): void
	{
		$this->assertMatches(['filter-a', 'filter-c'], ['or' => [['title' => 'Apple'], ['uid' => 'filter-c']]]);
		$this->assertMatches(['filter-b'], [
			'and' => [['rank' => ['exists' => true]], ['not' => ['title' => 'Apple']]],
		]);
		$this->assertMatches(['filter-a'], ['title' => ['ilike' => 'a%'], 'rank' => 3]);
	}

	public function testValuesNeedNoQuoting(): void
	{
		$this->assertMatches([], ['title' => "Apple' OR '1'='1"]);
		$this->assertMatches([], ['title' => ['in' => ["x'); DROP TABLE cms.nodes; --"]]]);
	}

	public function testInvalidFiltersAreRejected(): void
	{
		$invalid = [
			[],
			[['title' => 'Apple']],
			['title' => ['regex' => 'x']],
			['title' => ['lt' => null]],
			['title' => ['in' => []]],
			['title' => ['in' => [['nested']]]],
			['title' => ['exists' => 'yes']],
			['title' => ['null' => 1]],
			["title') OR 1=1 --" => 'x'],
			['or' => ['title' => 'x']],
			['not' => 'x'],
			['uid' => ['exists' => true]],
			['creator' => 'someone'],
			['title' => ['eq' => ['a' => 1]]],
			$this->nested(9),
		];

		foreach ($invalid as $filter) {
			try {
				iterator_to_array($this->createCms()->nodes()->types('test-widget')->match($filter, ['uid', 'parent']));
				$this->fail('Accepted ' . json_encode($filter));
			} catch (ParserException) {
				$this->addToAssertionCount(1);
			}
		}
	}

	/** @param array<array-key, mixed> $filter */
	private function assertMatches(array $uids, array $filter): void
	{
		$nodes = $this->createCms()->nodes()->types('test-widget')->match($filter)->order('uid');
		$found = array_map(static fn(object $node): string => $node->meta->uid, iterator_to_array($nodes));

		$this->assertSame($uids, $found, json_encode($filter));
	}

	/** @return array<string, mixed> */
	private function nested(int $depth): array
	{
		$filter = ['title' => 'x'];

		for ($i = 0; $i < $depth; $i++) {
			$filter = ['not' => $filter];
		}

		return $filter;
	}
}
