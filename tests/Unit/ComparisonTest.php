<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Context;
use Cosray\Exception\ParserOutputException;
use Cosray\Finder\QueryCompiler;
use Cosray\Tests\TestCase;

final class ComparisonTest extends TestCase
{
	private const string FIELD_JSON = "COALESCE(n.content->'field'->'value'->'en', n.content->'field'->'value'->'zxx')";
	private const string FIELD_DE_JSON = "n.content->'field'->'value'->'de'";
	private const string FIELD_TEXT = "COALESCE(NULLIF(n.content->'field'->'value'->>'en', ''), NULLIF(n.content->'field'->'value'->>'zxx', ''))";

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
			$this->jsonPath("\$ == \" \\\"\\\" '' \""),
			$compiler->compile('field = " \"\" \' "'),
		);

		$this->assertSame(
			$this->jsonPath("\$ == \"\\\"\\\"\\\"\""),
			$compiler->compile("field = '\"\"\"'"),
		);

		$this->assertSame(
			$this->jsonPath("\$ == \"test'' \\\" \\\" \""),
			$compiler->compile("field = 'test\\' \" \\\" '"),
		);

		$this->assertSame(
			$this->jsonPath('$ == "test\'\' \\\\"\\\\" \\"\\" \\"\\\\" \\\\"\\""'),
			$compiler->compile('field = \'test\\\' \\\"\\\" "" "\\\" \\\""\''),
		);
	}

	public function testNumberOperand(): void
	{
		$compiler = new QueryCompiler($this->context, []);

		$this->assertSame($this->jsonPath('$ == 13'), $compiler->compile('field = 13'));
		$this->assertSame(
			$this->jsonPath('$ == 13', self::FIELD_DE_JSON),
			$compiler->compile('field.value.de = 13'),
		);
		$this->assertSame($this->jsonPath('$ == 13.73'), $compiler->compile('field = 13.73'));
		$this->assertSame(
			$this->jsonPath('$ == 13.73', self::FIELD_DE_JSON),
			$compiler->compile('field.value.de = 13.73'),
		);
	}

	public function testStringOperand(): void
	{
		$compiler = new QueryCompiler($this->context, []);

		$this->assertSame(
			$this->jsonPath('$ == "string"'),
			$compiler->compile('field = "string"'),
		);
		$this->assertSame(
			$this->jsonPath('$ == "string"'),
			$compiler->compile("field = 'string'"),
		);
		$this->assertSame(
			$this->jsonPath('$ == "string"'),
			$compiler->compile('field = /string/'),
		);
		$this->assertSame(
			$this->jsonPath('$ == "string"', self::FIELD_DE_JSON),
			$compiler->compile("field.value.de = 'string'"),
		);
		$this->assertSame(
			$this->jsonPath('$ == "string"', self::FIELD_DE_JSON),
			$compiler->compile('field.value.de = "string"'),
		);
		$this->assertSame(
			$this->jsonPath('$ == "string"', self::FIELD_DE_JSON),
			$compiler->compile('field.value.de = /string/'),
		);
	}

	public function testBooleanOperand(): void
	{
		$compiler = new QueryCompiler($this->context, []);

		$this->assertSame($this->jsonPath('$ == false'), $compiler->compile('field = false'));
		$this->assertSame($this->jsonPath('$ == true'), $compiler->compile('field = true'));
		$this->assertSame(
			$this->jsonPath('$ == false', self::FIELD_DE_JSON),
			$compiler->compile('field.value.de = false'),
		);
		$this->assertSame(
			$this->jsonPath('$ == true', self::FIELD_DE_JSON),
			$compiler->compile('field.value.de = true'),
		);
	}

	public function testOperatorRegexOperandPattern(): void
	{
		$compiler = new QueryCompiler($this->context, []);

		$this->assertSame(
			$this->jsonPath('$ ? (@ like_regex "^test$")'),
			$compiler->compile('field ~ /^test$/'),
		);
		$this->assertSame(
			$this->jsonPath('$ ? (@ like_regex "^test$" flag "i")'),
			$compiler->compile('field ~* /^test$/'),
		);

		$this->assertSame(
			'NOT ' . $this->jsonPath('$ ? (@ like_regex "^test$")'),
			$compiler->compile('field !~ /^test$/'),
		);
		$this->assertSame(
			'NOT ' . $this->jsonPath('$ ? (@ like_regex "^test$" flag "i")'),
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
			self::FIELD_TEXT . " LIKE '%like\"%'",
			$compiler->compile('field ~~ "%like\"%"'),
		);
		$this->assertSame(
			self::FIELD_TEXT . " ILIKE '%ilike%'",
			$compiler->compile('field ~~* /%ilike%/'),
		);
		$this->assertSame(
			self::FIELD_TEXT . " NOT LIKE '%unlike'",
			$compiler->compile('field !~~ /%unlike/'),
		);
		$this->assertSame(
			self::FIELD_TEXT . " NOT ILIKE '%iunlike'",
			$compiler->compile('field !~~* /%iunlike/'),
		);

		$this->assertSame(
			'builtin LIKE ' . self::FIELD_TEXT,
			$compiler->compile('builtin ~~ field'),
		);
		$this->assertSame(
			self::FIELD_TEXT . ' LIKE builtin',
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
			$this->jsonPath('$ == "string"'),
			$compiler->compile('field="string"'),
		);
		$this->assertSame(
			$this->jsonPath('$ != "string"'),
			$compiler->compile('field!="string"'),
		);
		$this->assertSame($this->jsonPath('$ > 23'), $compiler->compile('field>23'));
		$this->assertSame($this->jsonPath('$ >= 23'), $compiler->compile('field>=23'));
		$this->assertSame($this->jsonPath('$ < 23'), $compiler->compile('field<23'));
		$this->assertSame($this->jsonPath('$ <= 23'), $compiler->compile('field<=23'));

		$this->assertSame('builtin > ' . self::FIELD_TEXT, $compiler->compile('builtin>field'));
		$this->assertSame(
			self::FIELD_TEXT . ' <= builtin',
			$compiler->compile('field<=builtin'),
		);
		$this->assertSame(
			self::FIELD_TEXT
			. " = COALESCE(NULLIF(n.content->'field2'->'value'->>'en', ''), NULLIF(n.content->'field2'->'value'->>'zxx', ''))",
			$compiler->compile('field=field2'),
		);
	}

	public function testMultilangFieldOperand(): void
	{
		$compiler = new QueryCompiler($this->context, []);

		$this->assertSame(
			$this->jsonPath('$[*] == "test"', "jsonb_path_query_array(n.content->'field'->'value', '$.*')"),
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

	private function jsonPath(string $path, string $field = self::FIELD_JSON): string
	{
		return 'jsonb_path_exists(' . $field . ', ' . $this->context->db->quote($path) . ')';
	}
}
