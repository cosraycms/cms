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
		self::assertStringContainsString('<mark>Apple</mark>', $results[0]->meta->search->snippet->html()->html);
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
		$this->store->hide($this->node('fts-hidden'), true, Actor::system());
		$this->store->published($this->node('fts-unpublished'), false, Actor::system());
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

	public function testAReaderSeesTypesItMayChangeWithoutAnActiveUrl(): void
	{
		$this->writer->create(
			$this->writer->prepare(FulltextDocument::class, ['body' => 'Needle'])->uid('fts-reader-document'),
		);
		$this->page('fts-reader-page', 'Needle');
		$this->sql('deletePaths', ['uid' => 'fts-reader-page'])->run();
		$found = fn(array $live, array $any): array => $this->uids(
			$this->cms->nodes()->published(null)->readable($live, $any)->fulltext('needle'),
		);

		self::assertSame([], $found(['fulltext-page', 'fulltext-document'], []));
		self::assertSame(['fts-reader-document'], $found(['fulltext-page'], ['fulltext-document']));
		// An explicit gate holds for every type.
		self::assertSame([], $this->uids(
			$this->cms
				->nodes()
				->published(null)
				->readable([], ['fulltext-document'])
				->fulltext('needle')
				->activeUrl(),
		));
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
		$html = $result->meta->search->snippet->html()->html;
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
		$this->store->applyDraft($this->node('fts-working'), $this->languages, Actor::system());
		self::assertSame(1, $this->cms->nodes()->fulltext('pendingword')->count());
		self::assertSame(0, $this->cms->nodes()->fulltext('publicword')->count());
	}

	public function testHidingAWorkingCopyImmediatelyHidesTheUnchangedLiveDocument(): void
	{
		$this->page('fts-hidden-draft', 'Liveword');
		$payload = $this->payload('fts-hidden-draft', 'Pendingword');
		$this->store->draft($this->node('fts-hidden-draft'), $payload, $this->languages, Actor::system());
		$this->store->hide($this->node('fts-hidden-draft'), true, Actor::system());
		self::assertSame('Liveword', $this->source('fts-hidden-draft'));
		self::assertSame(0, $this->cms->nodes()->fulltext('liveword')->count());
		self::assertSame(0, $this->cms->nodes()->fulltext('pendingword')->hidden(null)->count());
		self::assertSame(1, $this->cms->nodes()->fulltext('liveword')->hidden(null)->count());
	}

	public function testSearchingInsideALocaleLoopDoesNotMoveItsCursor(): void
	{
		$this->page('fts-locale-loop', 'Needle');
		$seen = [];
		foreach ($this->languages as $locale) {
			self::assertSame(1, $this->cms->nodes()->fulltext('needle')->count());
			$seen[] = $locale->id;
		}
		self::assertSame(['en', 'de'], $seen);
	}

	public function testSubstringSearchesKeepTheirPartialWordBehavior(): void
	{
		$this->page('fts-substring', 'Apple');
		self::assertSame(0, $this->cms->nodes()->fulltext('app')->count());
		self::assertSame(1, $this->cms->nodes()->searchTitle('app')->count());
		self::assertSame(1, $this->cms->nodes()->search('app', ['name'])->count());
	}

	public function testPartialWordsMatchInsideLongerWordsAndRankBelowWholeWords(): void
	{
		$this->page('fts-partial-a', 'Ball games');
		$this->page('fts-partial-b', 'Football');
		$this->page('fts-partial-c', 'Sports', 'A ball');
		$this->page('fts-partial-d', 'Weekend', 'Footballs and matches');
		$this->page('fts-partial-e', 'Tennis');
		$this->page('fts-partial-f', 'Footballs', 'A ball');
		self::assertSame(
			['fts-partial-a', 'fts-partial-c', 'fts-partial-f'],
			$this->uids($this->cms->nodes()->fulltext('ball')->order('uid')),
		);
		$finder = $this->cms->nodes()->fulltext('ball', partial: true);
		self::assertSame(5, $finder->count());
		$results = iterator_to_array($finder);
		self::assertSame(
			['fts-partial-a', 'fts-partial-b', 'fts-partial-f', 'fts-partial-c', 'fts-partial-d'],
			array_map(static fn($node): string => $node->meta->uid, $results),
		);
		self::assertSame([1.0, 0.5, 0.5, 0.1, 0.05], array_map(
			static fn($node): float => round($node->meta->search->score, 2),
			$results,
		));
		self::assertStringContainsString('<mark>Football</mark>', $results[1]->meta->search->snippet->html()->html);
		self::assertStringContainsString('<mark>Footballs</mark>', $results[4]->meta->search->snippet->html()->html);
		self::assertStringContainsString('<mark>ball</mark>', $results[3]->meta->search->snippet->html()->html);
		self::assertSame(['fts-partial-d'], $this->uids($this->cms->nodes()->fulltext('Foot match', partial: true)));
	}

	public function testPartialWordsIgnoreCaseAndAccents(): void
	{
		$this->page('fts-partial-accent', 'Ballétschool');
		$results = iterator_to_array($this->cms->nodes()->fulltext('ETSCH', partial: true));
		self::assertSame(['fts-partial-accent'], array_map(static fn($node): string => $node->meta->uid, $results));
		self::assertStringContainsString('<mark>Ballétschool</mark>', $results[0]->meta->search->snippet->html()->html);
	}

	#[DataProvider('partialQueries')]
	public function testPartialMatchingKeepsWebsearchIntent(string $query, array $expected): void
	{
		$this->page('fts-partial-a', 'Football', 'Weekend');
		$this->page('fts-partial-b', 'Baseball', 'Weekday');
		$this->page('fts-partial-c', 'Ballroom 100%', 'Dancing');
		$finder = $this->cms->nodes()->fulltext($query, partial: true)->order('uid');
		self::assertSame(
			array_map(static fn(string $id): string => 'fts-partial-' . $id, $expected),
			$this->uids($finder),
		);
		self::assertSame(count($expected), $finder->count());
	}

	public static function partialQueries(): array
	{
		return [
			'every word is required' => ['ball week', ['a', 'b']],
			'negation excludes partial matches' => ['ball -end', ['b', 'c']],
			'or widens to either word' => ['foot OR room', ['a', 'c']],
			'phrases beside words keep partial words' => ['"weekday" ball', ['b']],
			'short words only match whole' => ['ba', []],
			'phrases match exactly' => ['"ball"', []],
			'like wildcards are literal' => ['%', []],
			'percent signs are text' => ['100%', ['c']],
			'fullwidth percent is text' => ['b％d', []],
			'fullwidth low line is text' => ['Weekd＿y', []],
			'only negated words' => ['-ball', ['a', 'b', 'c']],
			'only stopwords' => ['the', []],
			'stopwords beside a word are ignored' => ['the ball', ['a', 'b', 'c']],
			'eight words still match partially' => [str_repeat('ball ', 8), ['a', 'b', 'c']],
			'more words fall back to whole words' => [str_repeat('ball ', 9), []],
		];
	}

	public function testASearchCanAlsoMatchTheDocument(): void
	{
		$this->page('fts-combo-title', 'Zebra');
		$this->page('fts-combo-body', 'Other', 'A zebra crossing');
		$this->page('zebra-combo-uid', 'Something else');
		$this->page('fts-combo-none', 'Nothing');
		$finder = fn(): Nodes => $this->cms->nodes()->search('zebra', ['uid', 'title'], fulltext: true);

		self::assertSame(3, $finder()->count());
		$uids = $this->uids($finder());
		self::assertSame('fts-combo-title', $uids[0]);
		self::assertEqualsCanonicalizing(['fts-combo-title', 'fts-combo-body', 'zebra-combo-uid'], $uids);
		// Only the document explains the body hit.
		self::assertSame(
			['fts-combo-body'],
			$this->uids($this->cms->nodes()->search('crossing', ['uid', 'title'], fulltext: true)),
		);
		// Without full text, the fields alone decide.
		self::assertEqualsCanonicalizing(
			['fts-combo-title', 'zebra-combo-uid'],
			$this->uids($this->cms->nodes()->search('zebra', ['uid', 'title'])),
		);
	}

	#[DataProvider('sourceQueries')]
	public function testEachWordMatchesInOneOfTheSources(string $query, array $expected): void
	{
		$this->page('fts-source-a', 'Zebra crossing', 'Cars stop and wait');
		$this->page('fts-source-b', 'Road works', 'Cars stop at the zebra');
		$this->page('fts-source-c', 'Café Q3 report');
		$this->page('fts-source-d', 'Aq3x 100%', 'Crossing zebra');
		$finder = $this->cms->nodes()->search($query, ['uid', 'title'], fulltext: true)->order('uid');

		self::assertSame(
			array_map(static fn(string $id): string => 'fts-source-' . $id, $expected),
			$this->uids($finder),
		);
		self::assertSame(count($expected), $finder->count());
	}

	public static function sourceQueries(): array
	{
		return [
			'a word in the title or the document' => ['zebra', ['a', 'b', 'd']],
			'words split across title and document' => ['zebra works', ['b']],
			'an excluded word in any source' => ['zebra -crossing', ['b']],
			'a phrase in the title' => ['"zebra crossing"', ['a']],
			'either word of an or group' => ['works OR report', ['b', 'c']],
			'a short word matches whole words only' => ['q3', ['c']],
			'accents fold in the title' => ['cafe', ['c']],
			'like wildcards are literal' => ['ze_ra', []],
			'punctuation around a word is dropped' => ['100%', ['d']],
			'a whole uid' => ['fts-source-c', ['c']],
			'the start of a uid' => ['fts-source', ['a', 'b', 'c', 'd']],
			'not the inside of a uid' => ['source-a', []],
			'punctuation only' => ['%', []],
		];
	}

	public function testFieldsAloneFoldAccentsAndMatchWordsInsideLongerOnes(): void
	{
		$this->page('fts-fields-cafe', 'Café Zebrastreifen');

		self::assertSame(['fts-fields-cafe'], $this->uids($this->cms->nodes()->search('CAFE strei', ['title'])));
		self::assertSame([], $this->uids($this->cms->nodes()->search('ca', ['title'])));
		// A picker searches while the editor types.
		self::assertSame(['fts-fields-cafe'], $this->uids($this->cms->nodes()->searchTitle('ca')));
	}

	public function testATypeMatchesByItsHandleOrLabel(): void
	{
		$this->page('fts-type-page', 'Notes');
		$this->writer->create(
			$this->writer->prepare(FulltextDocument::class, ['body' => 'Notes'])->uid('fts-type-document'),
		);
		$search = fn(string $query): array => $this->uids(
			$this->cms->nodes()->published(null)->search($query, ['title', 'type'])->order('uid'),
		);

		self::assertSame(['fts-type-document'], $search('document'));
		self::assertSame(['fts-type-page'], $search('fulltext-page'));
		self::assertSame(['fts-type-page'], $search('notes FulltextPage'));
		self::assertSame([], $search('notes FulltextDocument'));
		self::assertSame(['fts-type-page'], $search('-document'));
	}

	public function testATypeMatchRanksBelowAWholeWordInTheDocumentAboveAPartialOne(): void
	{
		$this->page('fts-type-rank-inside', 'Other', 'Superdocuments');
		$this->page('fts-type-rank-whole', 'Other', 'A document');
		$this->writer->create(
			$this->writer->prepare(FulltextDocument::class, ['body' => 'Notes'])->uid('fts-type-rank-type'),
		);

		self::assertSame(
			['fts-type-rank-whole', 'fts-type-rank-type', 'fts-type-rank-inside'],
			$this->uids($this->cms->nodes()->published(null)->search('document', ['title', 'type'], fulltext: true)),
		);
	}

	public function testANamedUidRanksFirstThenTitleThenDocumentMatches(): void
	{
		$this->page('fts-rank-body', 'Other', 'A zebra walks');
		$this->page('fts-rank-title', 'Zebra');
		$this->page('zebra', 'Unrelated');
		$this->page('fts-rank-inside', 'Zebrafish');

		self::assertSame(
			['zebra', 'fts-rank-title', 'fts-rank-inside', 'fts-rank-body'],
			$this->uids($this->cms->nodes()->search('zebra', ['uid', 'title'], fulltext: true)),
		);
	}

	public function testACombinedSearchNeedsNoActiveUrlAndOrdersTiesByTitle(): void
	{
		$this->writer->create(
			$this->writer->prepare(FulltextDocument::class, ['body' => 'quiet words'])->uid(
				'fts-combo-doc-b',
			),
		);
		$this->writer->create(
			$this->writer->prepare(FulltextDocument::class, ['body' => 'quiet words'])->uid(
				'fts-combo-doc-a',
			),
		);

		self::assertSame([], $this->uids($this->cms->nodes()->published(null)->fulltext('quiet')));
		// A type without a title map scores no title words instead of failing.
		self::assertCount(
			2,
			$this->uids($this->cms->nodes()->published(null)->activeUrl(false)->fulltext('quiet', partial: true)),
		);
		self::assertSame(
			['fts-combo-doc-a', 'fts-combo-doc-b'],
			$this->uids($this->cms->nodes()->published(null)->search('quiet', ['uid'], fulltext: true)),
		);
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
}
