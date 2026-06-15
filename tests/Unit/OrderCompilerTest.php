<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Exception\ParserException;
use Cosray\Finder\OrderCompiler;
use Cosray\Tests\TestCase;

const OB = "\n    ";

final class OrderCompilerTest extends TestCase
{
	public function testFailOnEmptyStatement(): void
	{
		$this->throws(ParserException::class, 'Empty order by clause');

		new OrderCompiler([])->compile('');
	}

	public function testCompileSimpleStatement(): void
	{
		$oc = new OrderCompiler([]);

		$this->assertSame(
			OB . "COALESCE(NULLIF(n.content->'test'->'value'->>'zxx', '')) ASC",
			$oc->compile('test'),
		);
	}

	public function testCompileStatementWithBuiltin(): void
	{
		$oc = new OrderCompiler(['field' => 'n.field']);

		$this->assertSame(OB . 'n.field ASC', $oc->compile('field'));
	}

	public function testCompileStatementWithDottedField(): void
	{
		$oc = new OrderCompiler([]);

		$this->assertSame(OB . "n.content->'test'->'value'->>'lang' ASC", $oc->compile('test.lang'));
		$this->assertSame(OB . "n.content->'test'->'lang'->>'de' ASC", $oc->compile('test.lang.de'));
	}

	public function testCompileMixedStatement(): void
	{
		$oc = new OrderCompiler(['field' => 'n.field']);
		$s = OB . "n.field ASC,\n    COALESCE(NULLIF(n.content->'test'->'value'->>'zxx', '')) ASC";

		$this->assertSame($s, $oc->compile('field, test'));
	}

	public function testChangeDirection(): void
	{
		$oc = new OrderCompiler([]);

		$this->assertSame(
			OB . "COALESCE(NULLIF(n.content->'test'->'value'->>'zxx', '')) DESC",
			$oc->compile('test desc'),
		);
	}

	public function testChangeDirectionWithBuiltin(): void
	{
		$oc = new OrderCompiler(['field' => 'n.field']);

		$this->assertSame(OB . 'n.field DESC', $oc->compile('field DeSc'));
	}

	public function testCompileLargerMixedStatement(): void
	{
		$oc = new OrderCompiler(['field' => 'n.field', 'column' => 'uc.column']);
		$s = ",\n    ";
		$result =
			OB
			. "n.field DESC{$s}COALESCE(NULLIF(n.content->'test'->'value'->>'zxx', '')) ASC{$s}"
			. "uc.column ASC{$s}n.content->'another'->'lang'->>'en' DESC";

		$this->assertSame($result, $oc->compile('field DESC, test asc, column, another.lang.en Desc'));
	}

	public function testFailOnInjection1(): void
	{
		$this->throws(ParserException::class, 'Invalid order by clause');

		$oc = new OrderCompiler();

		$oc->compile('; DROP TABLE students;');
	}

	public function testFailOnInjection2(): void
	{
		$this->throws(ParserException::class, 'Invalid order by clause');

		$oc = new OrderCompiler();

		$oc->compile('--');
	}

	public function testFailOnInjection3(): void
	{
		$this->throws(ParserException::class, 'Invalid order by clause');

		$oc = new OrderCompiler();

		$oc->compile('/*');
	}

	public function testFailInvalidField1(): void
	{
		$this->throws(ParserException::class, 'Invalid field name');

		$oc = new OrderCompiler();

		$oc->compile('field.to.');
	}

	public function testFailInvalidField2(): void
	{
		$this->throws(ParserException::class, 'Invalid order by clause');

		$oc = new OrderCompiler();

		$oc->compile('.field.to');
	}

	public function testFailInvalidField4(): void
	{
		$this->throws(ParserException::class, 'Invalid order by clause');

		$oc = new OrderCompiler();

		$oc->compile('field. .to');
	}

	public function testFailInvalidField3(): void
	{
		$this->throws(ParserException::class, 'Invalid field name');

		$oc = new OrderCompiler();

		$oc->compile('field..to');
	}

	public function testFailInvalidField5(): void
	{
		$this->throws(ParserException::class, 'Invalid field name');

		$oc = new OrderCompiler();

		$oc->compile('field.to. DESC');
	}

	public function testFailMultipleCommas(): void
	{
		$this->throws(ParserException::class, 'Invalid order by clause');

		$oc = new OrderCompiler();

		$oc->compile('field1,,field2');
	}
}
