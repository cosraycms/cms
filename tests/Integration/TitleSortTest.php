<?php

declare(strict_types=1);

namespace Cosray\Tests\Integration;

use Celema\Quma\Connection;
use Celema\Quma\Database;
use Celema\Quma\Delimiters;
use Cosray\Cms;
use Cosray\Context;
use Cosray\Field\Services;
use Cosray\Finder\Order;
use Cosray\Locales;
use Cosray\Node\Wrapper;
use Cosray\Tests\IntegrationTestCase;
use Cosray\Title\Indexes;
use Cosray\Title\Sort;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;

final class TitleSortTest extends IntegrationTestCase
{
	public function testFinderOrdersTheDisplayedFallbackTitles(): void
	{
		$this->loadFixtures('basic-types');
		$type = $this->createTestType('ordered-test-page');
		foreach ([
			'a' => ['fr-CA' => " \t\n\r\v", 'fr' => 'Alpha', 'en' => 'Zulu'],
			'b' => ['en' => 'Beta', 'zxx' => 'Zulu'],
			'c' => ['fr-CA' => 'Gamma', 'fr' => 'Alpha'],
			'd' => ['fr-CA' => 123, 'zxx' => 'Delta'],
			'e' => [],
		] as $uid => $title) {
			$this->createTestNode(['uid' => $uid, 'type' => $type]);
			$this->db()->execute('UPDATE cms.nodes SET title = :title::jsonb WHERE uid = :uid', [
				'uid' => $uid,
				'title' => json_encode((object) $title),
			])->run();
		}
		$locales = $this->localesWithFallback();
		$context = Context::console($this->db(), $this->config(), $this->container(), $this->factory(), $locales);
		$cms = new Cms($context, Services::withDefaults());

		foreach (['asc' => ['a', 'b', 'd', 'c', 'e'], 'desc' => ['c', 'd', 'b', 'a', 'e']] as $direction => $expected) {
			$nodes = iterator_to_array(
				$cms->nodes()->only('a', 'b', 'c', 'd', 'e')->order(new Order('title', $direction), new Order('uid')),
			);
			$this->assertSame($expected, array_map(static fn(Wrapper $node): string => $node->meta->uid, $nodes));
			$labels = array_map(static fn(Wrapper $node): string => $node->label(), array_slice($nodes, 0, 4));
			$this->assertSame(
				$direction === 'asc' ? ['Alpha', 'Beta', 'Delta', 'Gamma'] : ['Gamma', 'Delta', 'Beta', 'Alpha'],
				$labels,
			);
		}
	}

	public static function prefixes(): iterable
	{
		yield 'schema' => ['pg_temp.', '', 'pg_temp.nodes'];
		yield 'table prefix' => ['sorting_', 'sorting_', 'sorting_nodes'];
	}

	#[DataProvider('prefixes')]
	public function testReconciliationAndQueryUseTheSameIndex(string $prefix, string $objectPrefix, string $table): void
	{
		// All DDL is confined to a new temporary table on a separate connection.
		// Existing application/test indexes are never rebuilt by this test.
		$db = new Database(
			new Connection(self::testDbDsn(), self::root() . '/db/sql')
				->placeholders(Delimiters::comments(), ['pgsql' => [
					'cms.prefix' => $prefix,
					'cms.obj' => $objectPrefix,
				]])
				->fetch(PDO::FETCH_ASSOC),
		);
		$db->begin();
		try {
			$db->execute("CREATE TEMP TABLE {$table} (uid text, title jsonb)")->run();
			// An unmarked, old expression must be replaced even with the right name.
			$db->execute("CREATE INDEX {$objectPrefix}ix_nodes_title_fr_CA ON {$table} ((title->>'fr-CA'))")->run();
			$locales = $this->localesWithFallback();
			$indexes = new Indexes($db, $locales);
			$this->assertSame(
				['created' => 3, 'dropped' => 1, 'locales' => ['fr-CA', 'fr', 'en']],
				$indexes->reconcile(),
			);
			$this->assertSame(0, $indexes->reconcile()['created']);

			$expression = new Sort($db)->order($locales->get('fr-CA'));
			$db->execute('SET LOCAL enable_seqscan = off')->run();
			$explain = $db->execute("EXPLAIN (FORMAT JSON) SELECT uid FROM {$table} ORDER BY {$expression}")->one();
			$this->assertStringContainsString($objectPrefix . 'ix_nodes_title_fr_CA', json_encode($explain));

			// Fallback-only changes invalidate the expression under the same name.
			$locales->add('fr-CA', title: 'French Canadian', fallback: 'en');
			$this->assertSame(1, $indexes->reconcile()['created']);
			$this->assertSame(0, $indexes->reconcile()['created']);

			$onlyEnglish = new Locales();
			$onlyEnglish->add('en', title: 'English');
			$this->assertSame(
				['created' => 0, 'dropped' => 2, 'locales' => ['en']],
				new Indexes($db, $onlyEnglish)->reconcile(),
			);
		} finally {
			$db->rollback();
		}
	}

	private function localesWithFallback(): Locales
	{
		$locales = new Locales();
		$locales->add('fr-CA', title: 'French Canadian', fallback: 'fr');
		$locales->add('fr', title: 'French', fallback: 'en');
		$locales->add('en', title: 'English');
		return $locales;
	}
}
