<?php

declare(strict_types=1);

namespace Cosray\Tests\Integration;

use Cosray\Actor;
use Cosray\Finder\Nodes;
use Cosray\Fulltext\Search;
use Cosray\Tests\Fixtures\Node\FulltextDocument;
use Cosray\Tests\Fixtures\Node\FulltextPage;
use Cosray\Tests\FulltextTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class FulltextFinderTest extends FulltextTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->environment();
	}

	private function uids(Nodes $finder): array
	{
		return array_map(static fn($node): string => $node->meta->uid, iterator_to_array($finder));
	}

	public function testRankedResultsHaveQueryLocalMetadataAndStablePagination(): void
	{
		$this->page('fts-rank-c', 'Apple');
		$this->page('fts-rank-a', 'Apple');
		$this->page('fts-rank-b', 'Other title', 'An apple');
		$finder = $this->cms->nodes()->fulltext('apple');
		self::assertSame(3, $finder->count());
		$results = iterator_to_array($finder);
		self::assertSame(
			['fts-rank-a', 'fts-rank-c', 'fts-rank-b'],
			array_map(static fn($node): string => $node->meta->uid, $results),
		);
		self::assertInstanceOf(Search::class, $results[0]->meta->search);
		self::assertGreaterThan($results[2]->meta->search->score, $results[0]->meta->search->score);
		self::assertStringContainsString('<mark>Apple</mark>', $results[0]->meta->search->snippet->html());
		self::assertFalse(isset($this->cms->node->byUid('fts-rank-a')->meta->search));
		self::assertFalse(isset(iterator_to_array($this->cms->nodes()->only('fts-rank-a'))[0]->meta->search));
		$page = $this->cms->nodes()->fulltext('apple')->limit(1)->offset(1);
		self::assertSame(3, $page->count());
		self::assertSame(['fts-rank-c'], $this->uids($page));
		self::assertSame(
			['fts-rank-c', 'fts-rank-b', 'fts-rank-a'],
			$this->uids($this->cms->nodes()->fulltext('apple')->order('uid DESC')),
		);
	}

	public function testSearchComposesWithTypesFieldsAndHierarchy(): void
	{
		$this->page('fts-parent', 'Parent');
		$this->writer->create(
			$this->writer
				->prepare(FulltextPage::class, ['name' => ['en' => 'Apple'], 'category' => 'fruit'])
				->uid('fts-fruit')
				->published()
				->parent('fts-parent'),
		);
		$this->writer->create(
			$this->writer
				->prepare(FulltextPage::class, ['name' => ['en' => 'Apple'], 'category' => 'company'])
				->uid('fts-company')
				->published(),
		);
		$finder = $this->cms
			->nodes()
			->types(FulltextPage::class)
			->filter("category = 'fruit'")
			->childrenOf('fts-parent')
			->fulltext('apple');
		self::assertSame(1, $finder->count());
		self::assertSame(['fts-fruit'], $this->uids($finder));
		self::assertSame(0, $this->cms->nodes()->types(FulltextDocument::class)->fulltext('apple')->count());
	}

	public function testPublicGatesReadCurrentFlagsAndCanOnlyBeRelaxedExplicitly(): void
	{
		$this->page('fts-visible', 'Needle');
		$this->page('fts-hidden', 'Needle');
		$this->page('fts-unpublished', 'Needle');
		$this->page('fts-deleted', 'Needle');
		$this->db()->nodes->setHidden([
			'node' => $this->sql('node', ['uid' => 'fts-hidden'])->one()['node'],
			'hidden' => true,
			'editor' => 1,
		])->run();
		$this->store->unpublish($this->node('fts-unpublished'), $this->languages, Actor::system());
		// Leave a stale derived row to test the query boundary independently of synchronization.
		$this->sql('deleted', ['uid' => 'fts-deleted'])->run();
		$this->writer->create(
			$this->writer->prepare(FulltextDocument::class, ['body' => 'Needle'])->uid('fts-nonroutable')->published(),
		);
		$id = $this->sql('node', ['uid' => 'fts-nonroutable'])->one()['node'];
		$this->createTestPath($id, '/stale-nonroutable-path', 'en');
		self::assertSame(['fts-visible'], $this->uids($this->cms->nodes()->fulltext('needle')));
		self::assertSame(1, $this->cms->nodes()->fulltext('needle')->count());
		$all = $this->cms
			->nodes()
			->fulltext('needle')
			->published(null)
			->hidden(null)
			->deleted(null)
			->activeUrl(false)
			->order('uid');
		self::assertSame(5, $all->count());
		self::assertCount(5, $this->uids($all));
	}

	public function testActiveLocaleOrFallbackUrlsAreRequiredWithoutDuplicatingNodes(): void
	{
		foreach (['exact', 'fallback', 'missing', 'inactive', 'unrelated'] as $kind) {
			$uid = 'fts-path-' . $kind;
			$this->page($uid, 'Needle');
			$this->sql('deletePaths', ['uid' => $uid])->run();
			if ($kind === 'missing') {
				continue;
			}
			$id = $this->sql('node', ['uid' => $uid])->one()['node'];
			$locale = match ($kind) {
				'exact' => 'de',
				'unrelated' => 'fr',
				default => 'en',
			};
			$this->createTestPath($id, '/' . $uid, $locale);
			if ($kind === 'inactive') {
				$this->sql('inactive', ['uid' => $uid])->run();
			}
		}
		$this->context->withLocale($this->languages->get('de'), function (): void {
			$finder = $this->cms->nodes()->fulltext('needle')->order('uid');
			self::assertSame(2, $finder->count());
			$results = iterator_to_array($finder);
			self::assertSame(
				['fts-path-exact', 'fts-path-fallback'],
				array_map(static fn($node): string => $node->meta->uid, $results),
			);
			self::assertSame('/fts-path-fallback', $results[1]->path());
		});
	}

	#[DataProvider('queries')]
	public function testWebsearchSyntaxAndEmptyQueries(string $query, array $expected): void
	{
		$this->page('fts-query-a', 'Red fox', 'Apple banana');
		$this->page('fts-query-b', 'Blue fox', 'Apple');
		$this->page('fts-query-c', 'Red bird', 'Banana');
		$finder = $this->cms->nodes()->fulltext($query)->order('uid');
		self::assertSame(count($expected), $finder->count());
		self::assertSame(
			array_map(static fn(string $id): string => 'fts-query-' . $id, $expected),
			$this->uids($finder),
		);
	}

	public static function queries(): array
	{
		return [
			['apple banana', ['a']],
			['"red fox"', ['a']],
			['blue OR bird', ['b', 'c']],
			['apple -banana', ['b']],
			['-banana', ['b']],
			['apple OR -banana', ['a', 'b']],
			['', []],
			['!!!', []],
			['the and', []],
			['"', []],
			['""', []],
			["unmatched'); DELETE FROM cms.nodes; --", []],
		];
	}

	public function testExcludedTextCannotMatchOrLeakThroughSnippets(): void
	{
		$this->writer->create(
			$this->writer
				->prepare(FulltextPage::class, [
					'name' => ['en' => 'Café'],
					'private' => 'Confidential',
					'body' => ['en' => "\x01Café\x02 <script>alert('danger')</script> & tea"],
				])
				->uid('fts-safe')
				->published(),
		);
		self::assertSame(0, $this->cms->nodes()->fulltext('confidential')->count());
		$result = iterator_to_array($this->cms->nodes()->fulltext('cafe'))[0];
		$html = $result->meta->search->snippet->html();
		self::assertStringContainsString('<mark>Café</mark>', $html);
		self::assertStringNotContainsString('<script', $html);
		self::assertStringNotContainsString('Confidential', $html);
	}

	public function testWorkingCopyPublicationChangesPublicMatches(): void
	{
		$this->page('fts-working', 'Publicword');
		$this->store->draft(
			$this->node('fts-working'),
			$this->payload('fts-working', 'Pendingword'),
			$this->languages,
			Actor::system(),
		);
		self::assertSame(0, $this->cms->nodes()->fulltext('pendingword')->count());
		self::assertSame(1, $this->cms->nodes()->fulltext('publicword')->count());
		$this->store->publishDraft($this->node('fts-working'), $this->languages, Actor::system());
		self::assertSame(1, $this->cms->nodes()->fulltext('pendingword')->count());
		self::assertSame(0, $this->cms->nodes()->fulltext('publicword')->count());
	}

	public function testSubstringSearchesKeepTheirPartialWordBehavior(): void
	{
		$this->page('fts-substring', 'Apple');
		self::assertSame(0, $this->cms->nodes()->fulltext('app')->count());
		self::assertSame(1, $this->cms->nodes()->searchTitle('app')->count());
		self::assertSame(1, $this->cms->nodes()->search('app', ['name'])->count());
	}

	public function testFulltextRemainsAnOrdinaryFieldNameInTheFinderDsl(): void
	{
		$this->createTestNode([
			'uid' => 'fts-field-name',
			'type' => $this->createTestType('fulltext-page'),
			'content' => [
				'fulltext' => ['type' => \Cosray\Field\Text::class, 'value' => ['zxx' => 'field-value']],
			],
		]);
		$finder = $this->cms->nodes()->filter("fulltext = 'field-value'");
		self::assertSame(1, $finder->count());
		self::assertSame(['fts-field-name'], $this->uids($finder));
	}

	public function testHeadlinesAreEvaluatedOnlyForLimitPlusOffsetRows(): void
	{
		for ($i = 0; $i < 12; $i++) {
			$this->page('fts-plan-' . $i, 'Needle', str_repeat('Some searchable prose. ', 100));
		}
		$query = $this->db()->nodes->find([
			'fulltext' => 'needle',
			'search_locale' => 'en',
			'search_config' => 'cms.fts_english',
			'published' => true,
			'hidden' => false,
			'deleted' => false,
			'order' => 'search_score DESC, n.uid ASC',
			'limit' => 2,
			'offset' => 3,
		]);
		$sql = str_replace(
			'/*query*/',
			(string) $query,
			file_get_contents(self::root() . '/tests/Fixtures/sql/fulltext/explain.sql'),
		);
		$plan = json_decode(
			$this->db()->execute($sql)->one()['QUERY PLAN'],
			true,
			flags: JSON_THROW_ON_ERROR,
		)[0]['Plan'];
		$evaluations = [];
		$this->headlineEvaluations($plan, $evaluations);
		self::assertNotEmpty($evaluations, 'The plan must expose the headline evaluation node.');
		foreach ($evaluations as $rows) {
			self::assertLessThanOrEqual(5, $rows, 'Do not run ts_headline for every match before pagination.');
		}
	}

	private function headlineEvaluations(array $plan, array &$evaluations): bool
	{
		$hasHeadline = str_contains(implode(' ', $plan['Output'] ?? []), 'ts_headline(');
		$childHeadline = false;
		foreach ($plan['Plans'] ?? [] as $child) {
			$childHeadline = $this->headlineEvaluations($child, $evaluations) || $childHeadline;
		}
		if ($hasHeadline && !$childHeadline) {
			$evaluations[] = $plan['Actual Rows'] * $plan['Actual Loops'];
		}
		return $hasHeadline || $childHeadline;
	}
}
