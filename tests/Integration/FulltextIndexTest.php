<?php

declare(strict_types=1);

namespace Cosray\Tests\Integration;

use Cosray\Exception\RuntimeException;
use Cosray\Field\Text;
use Cosray\Fulltext\Configurations;
use Cosray\Fulltext\Snippet;
use Cosray\Locales;
use Cosray\Schema\Fulltext;
use Cosray\Schema\FulltextWeight as Weight;
use Cosray\Tests\FulltextTestCase;
use PDOException;

final class FulltextIndexTest extends FulltextTestCase
{
	private function locales(?string $dictionary = null): Locales
	{
		$locales = new Locales();
		$locales->add('en', 'English', pgDict: $dictionary);
		return $locales;
	}

	private function content(array $values): array
	{
		return array_map(static fn(string $text): array => ['value' => ['zxx' => $text]], $values);
	}

	private function index(array $values, ?string $dictionary = null): int
	{
		$node = $this->createTestNode(['type' => $this->createTestType(uniqid('fts-type-'))]);
		$this->sync()->replace(
			$node,
			'fts-index',
			FtsIndexContent::class,
			$this->content($values),
			[],
			$this->locales($dictionary),
		);
		return $node;
	}

	private function match(int $node, string $query, string $dictionary = 'simple'): ?array
	{
		return $this->sql('match', [
			'node' => $node,
			'query' => $query,
			'locale' => 'en',
			'config' => 'cms.fts_' . $dictionary,
		])->first();
	}

	public function testWeightsRankInPostgresqlOrderAndPhrasePositionsSpanContributions(): void
	{
		$node = $this->index(['a' => 'alpha first', 'b' => 'second beta', 'c' => 'gamma', 'd' => 'delta']);
		self::assertGreaterThan($this->match($node, 'beta')['score'], $this->match($node, 'alpha')['score']);
		self::assertGreaterThan($this->match($node, 'gamma')['score'], $this->match($node, 'beta')['score']);
		self::assertGreaterThan($this->match($node, 'delta')['score'], $this->match($node, 'gamma')['score']);
		self::assertNotNull($this->match($node, '"first second"'));
	}

	public function testMissingDictionaryUsesAccentInsensitiveSimpleWithoutStemmingOrStopwords(): void
	{
		$locale = $this->locales()->getDefault();
		self::assertSame('cms.fts_simple', new Configurations($this->db())->for($locale));
		$node = $this->index(['a' => 'Café running the']);
		$match = $this->match($node, 'cafe');
		self::assertNotNull($match);
		self::assertStringContainsString('<mark>Café</mark>', new Snippet($match['headline'])->html());
		self::assertNotNull($this->match($node, 'the'));
		self::assertNull($this->match($node, 'run'));
	}

	public function testConfiguredAnalyzersStemAndRemoveStopwords(): void
	{
		$node = $this->index(['a' => 'Café running the'], 'english');
		self::assertNotNull($this->match($node, 'run', 'english'));
		self::assertNull($this->match($node, 'the', 'english'));
		$node = $this->index(['a' => 'Häuser mit Gärten'], 'german');
		$match = $this->match($node, 'haus garten', 'german');
		self::assertNotNull($match);
		self::assertSame('<mark>Häuser</mark> mit <mark>Gärten</mark>', new Snippet($match['headline'])->html());
	}

	public function testUnknownConfigurationIsAnErrorBeforeReplacingExistingDocuments(): void
	{
		$node = $this->index(['a' => 'Keep me']);
		try {
			$this->sync()->replace(
				$node,
				'fts-index',
				FtsIndexContent::class,
				$this->content(['a' => 'Overwrite']),
				[],
				$this->locales('unavailable'),
			);
			self::fail('Expected a configuration error.');
		} catch (RuntimeException $e) {
			self::assertStringContainsString("'unavailable' for locale 'en'", $e->getMessage());
		}
		self::assertNotNull($this->match($node, 'keep'));
	}

	public function testReplacementRemovesOldLocalesAndEmptySelections(): void
	{
		$node = $this->index(['a' => 'Old words']);
		$locales = $this->locales();
		$locales->add('de', 'German', fallback: 'en');
		$this->sync()->replace(
			$node,
			'fts-index',
			FtsIndexContent::class,
			$this->content(['a' => 'New words']),
			[],
			$locales,
		);
		self::assertCount(2, $this->sql('inspect', ['node' => $node])->all());
		self::assertNull($this->match($node, 'old'));
		self::assertNotNull($this->match($node, 'new'));
		$this->sync()->replace(
			$node,
			'fts-index',
			FtsIndexContent::class,
			$this->content(['a' => 'Only English']),
			[],
			$this->locales(),
		);
		self::assertCount(1, $this->sql('inspect', ['node' => $node])->all());
		$this->sync()->replace($node, 'fts-index', FtsIndexContent::class, [], [], $this->locales());
		self::assertSame([], $this->sql('inspect', ['node' => $node])->all());
	}

	public function testOversizedVectorsFailWithNodeAndFieldAndRollbackRestoresTheIndex(): void
	{
		$node = $this->index(['a' => 'Previous version']);
		$text = implode(' ', array_map(
			static fn(int $i): string => 'lexeme' . str_pad((string) $i, 8, '0', STR_PAD_LEFT),
			range(1, 120000),
		));
		$this->sql('savepoint')->run();
		try {
			$this->sync()->replace(
				$node,
				'oversized-node',
				FtsIndexContent::class,
				$this->content(['a' => $text]),
				[],
				$this->locales(),
			);
			self::fail('Expected a PostgreSQL size error.');
		} catch (PDOException $e) {
			self::assertStringContainsString('node oversized-node', $e->getMessage());
			self::assertStringContainsString('field a', $e->getMessage());
			self::assertSame('54000', $e->getCode());
		} finally {
			$this->sql('rollback')->run();
		}
		self::assertNotNull($this->match($node, 'previous'));
	}

	public function testFreshInstallAndUpdateAnalyzeAndHighlightIdentically(): void
	{
		$this->sql('install-schema')->run();
		$schema = file_get_contents(self::root() . '/db/migrations/install/000000-000000-init-db-1[pgsql].sql');
		$this->db()->execute(strtr($schema, ['/*:cms.prefix:*/' => 'fts_install.', '/*:cms.obj:*/' => '']))->run();
		$rows = $this->sql('parity')->all();
		self::assertGreaterThan(20, count($rows));
		foreach ($rows as $row) {
			self::assertSame($row['updated'], $row['installed'], $row['cfgname']);
			self::assertSame($row['updated_headline'], $row['installed_headline'], $row['cfgname']);
		}
	}
}

class FtsIndexContent
{
	#[Fulltext(Weight::A)]
	protected Text $a;
	#[Fulltext(Weight::B)]
	protected Text $b;
	#[Fulltext(Weight::C)]
	protected Text $c;
	#[Fulltext(Weight::D)]
	protected Text $d;
}
