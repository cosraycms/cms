<?php

declare(strict_types=1);

namespace Celemas\Cms\Tests\Unit;

use Celemas\Cms\Context;
use Celemas\Cms\Exception\ParserException;
use Celemas\Cms\Finder\Output\Comparison;
use Celemas\Cms\Finder\Output\Exists;
use Celemas\Cms\Finder\Output\LeftParen;
use Celemas\Cms\Finder\Output\Operator;
use Celemas\Cms\Finder\Output\RightParen;
use Celemas\Cms\Finder\Output\UrlPath;
use Celemas\Cms\Finder\QueryParser;
use Celemas\Cms\Tests\TestCase;

final class QueryParserTest extends TestCase
{
	protected QueryParser $parser;

	protected function setUp(): void
	{
		$this->parser = new QueryParser(new Context(
			$this->db(),
			$this->request(),
			$this->config(),
			$this->container(),
			$this->factory(),
		), ['builtin' => 'c.builtin']);
	}

	public function testParseQuery(): void
	{
		$output = $this->parser->parse(
			'builtin = 13 & field & (field ~ "%like" | path != test) & field | field @ [1, 2, 3]',
		);

		$this->assertInstanceOf(Comparison::class, $output[0]);
		$this->assertInstanceOf(Operator::class, $output[1]);
		$this->assertInstanceOf(Exists::class, $output[2]);
		$this->assertInstanceOf(Operator::class, $output[3]);
		$this->assertInstanceOf(LeftParen::class, $output[4]);
		$this->assertInstanceOf(Comparison::class, $output[5]);
		$this->assertInstanceOf(Operator::class, $output[6]);
		$this->assertInstanceOf(UrlPath::class, $output[7]);
		$this->assertInstanceOf(RightParen::class, $output[8]);
		$this->assertInstanceOf(Operator::class, $output[9]);
		$this->assertInstanceOf(Exists::class, $output[10]);
		$this->assertInstanceOf(Operator::class, $output[11]);
		$this->assertInstanceOf(Comparison::class, $output[12]);
		$this->assertSame(false, isset($output[13]));
	}

	public function testInvalidPostionForOperator1(): void
	{
		$this->throws(ParserException::class, 'Invalid position for an operator');

		$this->parser->parse('( =');
	}

	public function testInvalidPostionForOperator2(): void
	{
		$this->throws(ParserException::class, 'Invalid position for an operator');

		$this->parser->parse('test = test ~');
	}

	public function testUnbalancedParenthesis1(): void
	{
		$this->throws(ParserException::class, 'Unbalanced parenthesis');

		$this->parser->parse('((test=1)');
	}

	public function testUnbalancedParenthesis2(): void
	{
		$this->throws(ParserException::class, 'Unbalanced parenthesis');

		$this->parser->parse('    )');
	}

	public function testUnbalancedParenthesis3(): void
	{
		$this->throws(ParserException::class, 'Unbalanced parenthesis');

		$this->parser->parse('(');
	}

	public function testInvalidCondition1Position(): void
	{
		$this->throws(ParserException::class, 'Invalid position for a condition');

		$this->parser->parse('1 = 1 1 = 1');
	}

	public function testInvalidCondition2MultipleOperators(): void
	{
		$this->throws(ParserException::class, 'Multiple operators');

		$this->parser->parse('1 = 1 | 1 == 1');
	}

	public function testInvalidCondition3GenerallyInvalid(): void
	{
		$this->throws(ParserException::class, 'Invalid condition');

		$this->parser->parse('1 = 1 | 1 1 =');
	}

	public function testInvalidCondition4BuiltinInExistsCondition(): void
	{
		$this->throws(ParserException::class, 'Conditions of type `field exists`');

		$this->parser->parse('1 = 1 | builtin');
	}

	public function testInvalidBooleanOperator1(): void
	{
		$this->throws(ParserException::class, 'Invalid position for a boolean operator');

		$this->parser->parse('field || 1 = 1');
	}

	public function testInvalidBooleanOperator2(): void
	{
		$this->throws(ParserException::class, 'Boolean operator at the end of the expression');

		$this->parser->parse('1 = 1 |');
	}

	public function testInvalidParenthesis1(): void
	{
		$this->throws(ParserException::class, 'Invalid parenthesis: empty group');

		$this->parser->parse('1 = 1 | ()');
	}

	public function testInvalidParenthesis2(): void
	{
		$this->throws(ParserException::class, 'Invalid position for parenthesis');

		$this->parser->parse('1 = 1 (1 = 1)');
	}
}
