<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Tests\TestCase;
use Cosray\Title\Sort;

/**
 * @internal
 *
 * @coversNothing
 */
final class TitleSortTest extends TestCase
{
	public function testExpressionFallsBackToNeutralAndTreatsBlanksAsAbsent(): void
	{
		$this->assertSame(
			"COALESCE(NULLIF(title->>'de', ''), NULLIF(title->>'zxx', ''))",
			Sort::expression('de'),
		);
	}

	public function testExpressionTakesAColumn(): void
	{
		$this->assertSame(
			"COALESCE(NULLIF(n.title->>'en', ''), NULLIF(n.title->>'zxx', ''))",
			Sort::expression('en', 'n.title'),
		);
	}

	public function testCollationAndIndexNaming(): void
	{
		$this->assertSame('de-x-icu', Sort::collation('de'));
		$this->assertSame('ix_nodes_title_de', Sort::indexName('de'));
		$this->assertSame('ix_nodes_title_pt_BR', Sort::indexName('pt-BR'));
	}

	public function testValidRejectsUnsafeLocaleIds(): void
	{
		$this->assertTrue(Sort::valid('de'));
		$this->assertTrue(Sort::valid('pt-BR'));
		$this->assertFalse(Sort::valid("de'; DROP"));
		$this->assertFalse(Sort::valid('1de'));
		$this->assertFalse(Sort::valid(''));
	}
}
