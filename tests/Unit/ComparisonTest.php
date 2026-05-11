<?php

declare(strict_types=1);

namespace Celemas\Cms\Tests\Unit;

use Celemas\Cms\Context;
use Celemas\Cms\Exception\ParserOutputException;
use Celemas\Cms\Finder\QueryCompiler;
use Celemas\Cms\Tests\TestCase;

final class ComparisonTest extends TestCase
{
	private Context $context;

	protected function setUp(): void
	{
		$this->context = new Context(
			$this->db(),
			$this->request(),
			$this->config(),
			$this->container(),
			$this->factory(),
		);
	}

	public function testJsonStringQuoting(): void
	{
		$compiler = new QueryCompiler($this->context, []);

		$this->assertSame(
			'n.content @@ \'$.field.value == " \"\" \'\' "\'',
			$compiler->compile('field = " \"\" \' "'),
		);

		$this->assertSame(
			'n.content @@ \'$.field.value == "\"\"\""\'',
			$compiler->compile("field = '\"\"\"'"),
		);

		$this->assertSame(
			'n.content @@ \'$.field.value == "test\'\' \" \" "\'',
			$compiler->compile("field = 'test\\' \" \\\" '"),
		);

		$this->assertSame(
			'n.content @@ \'$.field.value == "test\'\' \"\" \"\" \"\" \"\""\'',
			$compiler->compile('field = \'test\\\' \\"\\" "" "\\" \\""\''),
		);
	}

	public function testNumberOperand(): void
	{
		$compiler = new QueryCompiler($this->context, []);

		$this->assertSame("n.content @@ '$.field.value == 13'", $compiler->compile('field = 13'));
		$this->assertSame(
			"n.content @@ '$.field.value.de == 13'",
			$compiler->compile('field.value.de = 13'),
		);
		$this->assertSame("n.content @@ '$.field.value == 13.73'", $compiler->compile('field = 13.73'));
		$this->assertSame(
			"n.content @@ '$.field.value.de == 13.73'",
			$compiler->compile('field.value.de = 13.73'),
		);
	}

	public function testStringOperand(): void
	{
		$compiler = new QueryCompiler($this->context, []);

		$this->assertSame(
			'n.content @@ \'$.field.value == "string"\'',
			$compiler->compile('field = "string"'),
		);
		$this->assertSame(
			'n.content @@ \'$.field.value == "string"\'',
			$compiler->compile("field = 'string'"),
		);
		$this->assertSame(
			'n.content @@ \'$.field.value == "string"\'',
			$compiler->compile('field = /string/'),
		);
		$this->assertSame(
			'n.content @@ \'$.field.value.de == "string"\'',
			$compiler->compile("field.value.de = 'string'"),
		);
		$this->assertSame(
			'n.content @@ \'$.field.value.de == "string"\'',
			$compiler->compile('field.value.de = "string"'),
		);
		$this->assertSame(
			'n.content @@ \'$.field.value.de == "string"\'',
			$compiler->compile('field.value.de = /string/'),
		);
	}

	public function testBooleanOperand(): void
	{
		$compiler = new QueryCompiler($this->context, []);

		$this->assertSame("n.content @@ '$.field.value == false'", $compiler->compile('field = false'));
		$this->assertSame("n.content @@ '$.field.value == true'", $compiler->compile('field = true'));
		$this->assertSame(
			"n.content @@ '$.field.value.de == false'",
			$compiler->compile('field.value.de = false'),
		);
		$this->assertSame(
			"n.content @@ '$.field.value.de == true'",
			$compiler->compile('field.value.de = true'),
		);
	}

	public function testOperatorRegexOperandPattern(): void
	{
		$compiler = new QueryCompiler($this->context, []);

		$this->assertSame(
			"n.content @? '$.field.value ? (@ like_regex \"^test$\")'",
			$compiler->compile('field ~ /^test$/'),
		);
		$this->assertSame(
			"n.content @? '$.field.value ? (@ like_regex \"^test$\" flag \"i\")'",
			$compiler->compile('field ~* /^test$/'),
		);

		$this->assertSame(
			"NOT n.content @? '$.field.value ? (@ like_regex \"^test$\")'",
			$compiler->compile('field !~ /^test$/'),
		);
		$this->assertSame(
			"NOT n.content @? '$.field.value ? (@ like_regex \"^test$\" flag \"i\")'",
			$compiler->compile('field !~* /^test$/'),
		);
	}

	public function testOperatorLikeAndIlike(): void
	{
		$compiler = new QueryCompiler($this->context, ['builtin' => 'builtin']);

		$this->assertSame("builtin LIKE '%like\"%'", $compiler->compile('builtin ~~ "%like\"%"'));
		$this->assertSame("builtin ILIKE '%ilike%'", $compiler->compile('builtin ~~* /%ilike%/'));
		$this->assertSame("builtin NOT LIKE '%unlike'", $compiler->compile('builtin !~~ /%unlike/'));
		$this->assertSame("builtin NOT ILIKE '%iunlike'", $compiler->compile('builtin !~~* /%iunlike/'));

		$this->assertSame(
			"n.content->'field'->>'value' LIKE '%like\"%'",
			$compiler->compile('field ~~ "%like\"%"'),
		);
		$this->assertSame(
			"n.content->'field'->>'value' ILIKE '%ilike%'",
			$compiler->compile('field ~~* /%ilike%/'),
		);
		$this->assertSame(
			"n.content->'field'->>'value' NOT LIKE '%unlike'",
			$compiler->compile('field !~~ /%unlike/'),
		);
		$this->assertSame(
			"n.content->'field'->>'value' NOT ILIKE '%iunlike'",
			$compiler->compile('field !~~* /%iunlike/'),
		);

		$this->assertSame(
			"builtin LIKE n.content->'field'->>'value'",
			$compiler->compile('builtin ~~ field'),
		);
		$this->assertSame(
			"n.content->'field'->>'value' LIKE builtin",
			$compiler->compile('field ~~ builtin'),
		);
	}

	public function testRemainingOperators(): void
	{
		$compiler = new QueryCompiler($this->context, ['builtin' => 'builtin']);

		$this->assertSame("builtin = 'string'", $compiler->compile('builtin="string"'));
		$this->assertSame("builtin != 'string'", $compiler->compile('builtin!="string"'));
		$this->assertSame('builtin > 23', $compiler->compile('builtin>23'));
		$this->assertSame('builtin >= 23', $compiler->compile('builtin>=23'));
		$this->assertSame('builtin < 23', $compiler->compile('builtin<23'));
		$this->assertSame('builtin <= 23', $compiler->compile('builtin<=23'));

		$this->assertSame(
			'n.content @@ \'$.field.value == "string"\'',
			$compiler->compile('field="string"'),
		);
		$this->assertSame(
			'n.content @@ \'$.field.value != "string"\'',
			$compiler->compile('field!="string"'),
		);
		$this->assertSame('n.content @@ \'$.field.value > 23\'', $compiler->compile('field>23'));
		$this->assertSame('n.content @@ \'$.field.value >= 23\'', $compiler->compile('field>=23'));
		$this->assertSame('n.content @@ \'$.field.value < 23\'', $compiler->compile('field<23'));
		$this->assertSame('n.content @@ \'$.field.value <= 23\'', $compiler->compile('field<=23'));

		$this->assertSame("builtin > n.content->'field'->>'value'", $compiler->compile('builtin>field'));
		$this->assertSame(
			"n.content->'field'->>'value' <= builtin",
			$compiler->compile('field<=builtin'),
		);
		$this->assertSame(
			"n.content->'field'->>'value' = n.content->'field2'->>'value'",
			$compiler->compile('field=field2'),
		);
	}

	public function testMultilangFieldOperand(): void
	{
		$compiler = new QueryCompiler($this->context, []);

		$this->assertSame(
			"n.content @@ '$.field.value.* == \"test\"'",
			$compiler->compile('field.* = "test"'),
		);
	}

	public function testBuiltinOperand(): void
	{
		$compiler = new QueryCompiler($this->context, ['test' => 'table.test']);

		$this->assertSame('table.test = 1', $compiler->compile('test = 1'));
	}

	public function testKeywordNow(): void
	{
		$compiler = new QueryCompiler($this->context, ['test' => 'test']);

		$this->assertSame('test = NOW()', $compiler->compile('test = now'));
	}

	public function testRejectLiteralOnLeftSide(): void
	{
		$this->throws(ParserOutputException::class, 'Only fields or ');

		$compiler = new QueryCompiler($this->context, []);

		$compiler->compile('"string" = 1');
	}
}
