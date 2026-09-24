<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Exception\RuntimeException;
use Cosray\Tests\TestCase;
use Cosray\Title\Sort;

final class TitleSortTest extends TestCase
{
	public function testCollationSelectionUsesLocaleThenRootThenDatabaseDefault(): void
	{
		$available = ['de-x-icu' => 'pg_catalog."de-x-icu"', 'und-x-icu' => 'pg_catalog."und-x-icu"'];
		$this->assertSame('pg_catalog."de-x-icu"', Sort::chooseCollation('de', $available));
		$this->assertSame('pg_catalog."und-x-icu"', Sort::chooseCollation('fr', $available));
		$this->assertNull(Sort::chooseCollation('de', []));
	}

	public function testCollationAndIndexNaming(): void
	{
		$this->assertSame('de-x-icu', Sort::collation('de'));
		$this->assertSame('pt-BR-x-icu', Sort::collation('pt_BR'));
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
		$this->expectException(RuntimeException::class);
		Sort::expression("de'; DROP");
	}
}
