<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Fulltext\Query;
use Cosray\Fulltext\Term;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FulltextQueryTest extends TestCase
{
	/** @param list<list<string>> $expected groups of terms, `-` excluded, quoted phrases, `*` matching inside words */
	#[DataProvider('queries')]
	public function testReadsTheQueryAsWebsearchDoes(string $query, array $expected): void
	{
		self::assertSame($expected, array_map(
			static fn(array $group): array => array_map(
				static fn(Term $term): string => (
					($term->negated ? '-' : '')
					. ($term->phrase ? '"' . $term->text . '"' : $term->text)
					. ($term->inside ? '*' : '')
				),
				$group,
			),
			Query::parse($query)->groups,
		));
	}

	public static function queries(): array
	{
		return [
			'words are required' => ['red fox', [['red*'], ['fox*']]],
			'short words match whole' => ['an ox', [['an'], ['ox']]],
			'phrases match whole' => ['"red  fox" den', [['"red fox"'], ['den*']]],
			'an unclosed phrase runs to the end' => ['den "red fox', [['den*'], ['"red fox"']]],
			'or joins its neighbours' => ['red OR blue fox', [['red*', 'blue*'], ['fox*']]],
			'or in any case' => ['red or blue', [['red*', 'blue*']]],
			'or chains' => ['red OR blue OR green', [['red*', 'blue*', 'green*']]],
			'a dangling or is ignored' => ['OR red OR', [['red*']]],
			'exclusions' => ['fox -red -"blue fox"', [['fox*'], ['-red*'], ['-"blue fox"']]],
			'an excluded alternative' => ['apple OR -banana', [['apple*', '-banana*']]],
			'punctuation around words is dropped' => ['(fox), 100%', [['fox*'], ['100*']]],
			'punctuation inside words stays' => ['e-mail a@b.de', [['e-mail*'], ['a@b.de*']]],
			'punctuation only' => ['!!! - "" %', []],
			'eight terms match inside' => [str_repeat('fox ', 8), array_fill(0, 8, ['fox*'])],
			'more terms match whole' => [str_repeat('fox ', 9), array_fill(0, 9, ['fox'])],
		];
	}

	public function testATypeAheadMatchesInsideWordsFromTheFirstLetter(): void
	{
		self::assertTrue(Query::parse('a', inside: 1)->groups[0][0]->inside);
	}

	public function testRequiredGroupsHaveAnAlternativeThatIsNotExcluded(): void
	{
		$required = Query::parse('fox -red apple OR -banana')->required();

		self::assertCount(2, $required);
		self::assertSame('fox', $required[0][0]->text);
		self::assertSame('apple', $required[1][0]->text);
	}
}
