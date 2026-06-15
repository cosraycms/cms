<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Context;
use Cosray\Exception\ParserException;
use Cosray\Exception\ParserOutputException;
use Cosray\Finder\QueryCompiler;
use Cosray\Tests\TestCase;

final class QueryCompilerTest extends TestCase
{
	private const string FIELD_JSON = "COALESCE(n.content->'field'->'value'->'en', n.content->'field'->'value'->'zxx')";
	private const string FIELD_TEXT = "COALESCE(NULLIF(n.content->'field'->'value'->>'en', ''), NULLIF(n.content->'field'->'value'->>'zxx', ''))";

	private Context $context;

	protected function setup(): void
	{
		$this->context = new Context(
			$this->db(),
			$this->request(),
			$this->config(),
			$this->container(),
			$this->factory(),
		);
	}

	public function testSimpleAndQuery(): void
	{
		$compiler = new QueryCompiler($this->context, ['builtin' => 'builtin']);

		$this->assertSame(
			$this->jsonPath('$ == 1') . ' AND builtin = 2',
			$compiler->compile('field=1 & builtin=2'),
		);
	}

	public function testInAndNotInQueryWithBuiltin(): void
	{
		$compiler = new QueryCompiler($this->context, ['builtin' => 'builtin']);

		$this->assertSame(
			"builtin IN ('v1', 'v\"2', 'v3''')",
			$compiler->compile("builtin @ ['v1'  , 'v\"2''v3\'']"),
		);
		$this->assertSame(
			"builtin IN ('1', '2', '3', '4')",
			$compiler->compile('builtin @ [,1, 2,3 4]'),
		);

		$this->assertSame(
			"builtin NOT IN ('''v1', 'v2', 'v3')",
			$compiler->compile("builtin !@ ['\'v1''v2''v3']"),
		);
		$this->assertSame(
			"builtin NOT IN ('1', '2', '3', '4')",
			$compiler->compile('builtin !@ [1    2  3,,4]'),
		);
	}

	public function testInAndNotInQueryWithField(): void
	{
		$compiler = new QueryCompiler($this->context, ['builtin' => 'builtin']);

		$this->assertSame(
			self::FIELD_TEXT . " IN ('v1', 'v2', 'v''3', 'v4')",
			$compiler->compile("field @ ['v1', 'v2' , 'v\'3''v4' ,]"),
		);
		$this->assertSame(
			self::FIELD_TEXT . " IN ('1', '2', '3', '4.513')",
			$compiler->compile('field @ [1,2 , 3 4.513]'),
		);

		$this->assertSame(
			self::FIELD_TEXT . " NOT IN ('v1', 'v2', 'v3', 'v4')",
			$compiler->compile("field !@ [, 'v1''v2''v3''v4' ,]"),
		);
		$this->assertSame(
			self::FIELD_TEXT . " NOT IN ('1', '0.0002', '3', '4')",
			$compiler->compile('field !@ [, 1 0.0002 , 3 , ,4 ,]'),
		);
	}

	public function testSimpleOrQuery(): void
	{
		$compiler = new QueryCompiler($this->context, ['builtin' => 'builtin']);

		$this->assertSame(
			$this->jsonPath('$ == 1') . ' OR builtin = 2',
			$compiler->compile('field=1 | builtin=2'),
		);
	}

	public function testNestedQuery1(): void
	{
		$compiler = new QueryCompiler($this->context, ['builtin' => 'n.builtin']);

		$this->assertSame(
			$this->jsonPath('$ == 1') . ' AND (n.builtin = 2 OR n.builtin = 3)',
			$compiler->compile('field=1 & (builtin=2|builtin=3)'),
		);
	}

	public function testNestedQuery2(): void
	{
		$compiler = new QueryCompiler($this->context, [
			'builtin' => 'n.builtin',
			'another' => 't.another',
		]);

		$this->assertSame(
			$this->jsonPath('$ == 1') . " AND (t.another = 'test' OR (n.builtin > 2 AND n.builtin < 5))",
			$compiler->compile("field=1 & (another='test'|(builtin>2 & builtin<5))"),
		);
	}

	public function testNestedQuery3(): void
	{
		$compiler = new QueryCompiler($this->context, [
			'builtin' => 'n.builtin',
			'another' => 't.another',
		]);

		$this->assertSame(
			'(n.builtin = 1 OR '
			. $this->jsonPath('$ == 1')
			. ')'
			. ' AND '
			. "(t.another = 'test' OR (n.builtin > 2 AND n.builtin < 5))",
			$compiler->compile("(builtin = 1 | field=1) & (another='test'|(builtin>2 & builtin<5))"),
		);
	}

	public function testNullQuery(): void
	{
		$compiler = new QueryCompiler($this->context, ['builtin' => 'n.builtin']);

		$this->assertSame('n.builtin IS NULL', $compiler->compile('builtin = null'));
	}

	public function testNotNullQuery(): void
	{
		$compiler = new QueryCompiler($this->context, ['builtin' => 'n.builtin']);

		$this->assertSame('n.builtin IS NOT NULL', $compiler->compile('builtin != null'));
	}

	public function testNullQueryWrongPosition(): void
	{
		$this->throws(
			ParserException::class,
			'Parse error at position 1. Invalid position for a null value.',
		);

		$compiler = new QueryCompiler($this->context, ['builtin' => 'n.builtin']);

		$compiler->compile('null = builtin');
	}

	public function testNullQueryWrongOperant(): void
	{
		$this->throws(
			ParserOutputException::class,
			'Only equal (=) or unequal (!=) operators are allowed',
		);

		$compiler = new QueryCompiler($this->context, ['builtin' => 'n.builtin']);

		$compiler->compile('builtin ~ null');
	}

	public function testExistsQuery(): void
	{
		$compiler = new QueryCompiler($this->context, []);

		$this->assertSame(
			"n.content @? '$.field.value.*'",
			$compiler->compile('field'),
		);
		$this->assertSame(
			"n.content @? '$.field.value.en'",
			$compiler->compile('field.?'),
		);
		$this->assertSame(
			"n.content @? '$.field.value.de'",
			$compiler->compile('field.value.de'),
		);
	}

	public function testPathQuery(): void
	{
		$compiler = new QueryCompiler($this->context, []);

		$this->assertSame(
			"EXISTS (SELECT 1 FROM cms.url_paths p WHERE p.node = n.node AND p.inactive IS NULL AND p.path = '/about')",
			$compiler->compile("path = '/about'"),
		);
		$this->assertSame(
			"EXISTS (SELECT 1 FROM cms.url_paths p WHERE p.node = n.node AND p.inactive IS NULL AND p.locale = 'de' AND p.path = '/ueber-uns')",
			$compiler->compile("path.de = '/ueber-uns'"),
		);
		$this->assertSame(
			"NOT EXISTS (SELECT 1 FROM cms.url_paths p WHERE p.node = n.node AND p.inactive IS NULL AND p.path = '/hidden')",
			$compiler->compile("path != '/hidden'"),
		);
		$this->assertSame(
			"EXISTS (SELECT 1 FROM cms.url_paths p WHERE p.node = n.node AND p.inactive IS NULL AND p.path = '/about')",
			$compiler->compile("'/about' = path"),
		);
	}

	private function jsonPath(string $path): string
	{
		return 'jsonb_path_exists(' . self::FIELD_JSON . ', ' . $this->context->db->quote($path) . ')';
	}
}
