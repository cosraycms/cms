<?php

declare(strict_types=1);

namespace Celemas\Cms\Tests\Unit;

use Celemas\Cms\Exception\ParserException;
use Celemas\Cms\Finder\QueryLexer;
use Celemas\Cms\Tests\TestCase;

const QUERY_ALL_ELEMENTS =
	'(true = field1 & builtin1>now&null >=   13 & field2 < "string") |'
	. '(13.73 <= builtin2 | field3 ~ "%string" | builtin3!~"string%" | path.de-DE != 31 | '
	. ' path !~~ \'url\' &field4 ~~\'%str%\' & field5 ~* "(a|b)" & field6 !~* "(a|b)" | '
	. ' field7 ~~* /%abc%/ | field8 !~~* /%a\\/bc/)';

final class QueryLexerTest extends TestCase
{
	public function testSimpleQuery(): void
	{
		$lexer = new QueryLexer();
		$tokens = $lexer->tokens('field = test');

		$this->assertSame('Field', $tokens[0]->type->name);
		$this->assertSame('Equal', $tokens[1]->type->name);
		$this->assertSame('Field', $tokens[2]->type->name);
	}

	public function testSimpleQueryWithSingleQuoteString(): void
	{
		$lexer = new QueryLexer();
		$tokens = $lexer->tokens("field = 'test'");

		$this->assertSame('Field', $tokens[0]->type->name);
		$this->assertSame('Equal', $tokens[1]->type->name);
		$this->assertSame('String', $tokens[2]->type->name);
	}

	public function testSimpleQueryWithDoubleQuoteString(): void
	{
		$lexer = new QueryLexer();
		$tokens = $lexer->tokens('field = "test"');

		$this->assertSame('Field', $tokens[0]->type->name);
		$this->assertSame('Equal', $tokens[1]->type->name);
		$this->assertSame('String', $tokens[2]->type->name);
	}

	public function testSimpleQueryWithPatternString(): void
	{
		$lexer = new QueryLexer();
		$tokens = $lexer->tokens('field = /test/');

		$this->assertSame('Field', $tokens[0]->type->name);
		$this->assertSame('Equal', $tokens[1]->type->name);
		$this->assertSame('String', $tokens[2]->type->name);
	}

	public function testSimpleQueryWithSingleQuoteStringAndEscape(): void
	{
		$lexer = new QueryLexer();
		$tokens = $lexer->tokens("field = '\"test\"\\'st/r\\ing\\'test'");

		$this->assertSame('Field', $tokens[0]->type->name);
		$this->assertSame('Equal', $tokens[1]->type->name);
		$this->assertSame('String', $tokens[2]->type->name);
		$this->assertSame('"test"\'st/r\\ing\'test', $tokens[2]->lexeme);
	}

	public function testSimpleQueryWithDoubleQuoteStringAndEscape(): void
	{
		$lexer = new QueryLexer();
		$tokens = $lexer->tokens('field = "\'test\'\\"str\\ing\\"test"');

		$this->assertSame('Field', $tokens[0]->type->name);
		$this->assertSame('Equal', $tokens[1]->type->name);
		$this->assertSame('String', $tokens[2]->type->name);
		$this->assertSame("'test'\"str\\ing\"test", $tokens[2]->lexeme);
	}

	public function testSimpleQueryWithPatternStringAndEscape(): void
	{
		$lexer = new QueryLexer();
		$tokens = $lexer->tokens('field = /\'test\'\\/st"r\\i"ng\\/test/');

		$this->assertSame('Field', $tokens[0]->type->name);
		$this->assertSame('Equal', $tokens[1]->type->name);
		$this->assertSame('String', $tokens[2]->type->name);
		$this->assertSame("'test'/st\"r\\i\"ng/test", $tokens[2]->lexeme);
	}

	public function testSimpleQueryWithSpecialCharacterInIdentifier(): void
	{
		$lexer = new QueryLexer();
		$tokens = $lexer->tokens(
			'field.* = "test" | field.? = "test" | field.*.test = 1 | field.?.test = 1',
		);

		$this->assertSame('field.*', $tokens[0]->lexeme);
		$this->assertSame('field.?', $tokens[4]->lexeme);
		$this->assertSame('field.*.test', $tokens[8]->lexeme);
		$this->assertSame('field.?.test', $tokens[12]->lexeme);
	}

	public function testListQuery(): void
	{
		$lexer = new QueryLexer();
		$tokens = $lexer->tokens('field @ ["chuck", "schuldiner", 13]');

		$this->assertSame('Field', $tokens[0]->type->name);
		$this->assertSame('In', $tokens[1]->type->name);
		$this->assertSame('LeftBracket', $tokens[2]->type->name);
		$this->assertSame('String', $tokens[3]->type->name);
		$this->assertSame('String', $tokens[4]->type->name);
		$this->assertSame('Number', $tokens[5]->type->name);
		$this->assertSame('RightBracket', $tokens[6]->type->name);
	}

	public function testInvalidDot1(): void
	{
		$this->throws(ParserException::class, 'Invalid use of dot');

		$lexer = new QueryLexer();
		$lexer->tokens('field. = "test"');
	}

	public function testInvalidDot2(): void
	{
		$this->throws(ParserException::class, 'Invalid use of dot');

		$lexer = new QueryLexer();
		$lexer->tokens('field..test = "test"');
	}

	public function testInvalidDot3(): void
	{
		$this->throws(ParserException::class, 'Syntax error');

		$lexer = new QueryLexer();
		$lexer->tokens('.field = "test"');
	}

	public function testInvalidSpecialChar1(): void
	{
		$this->throws(ParserException::class, 'Invalid use of special');

		$lexer = new QueryLexer();
		$lexer->tokens('field.*h = "test"');
	}

	public function testInvalidSpecialChar2(): void
	{
		$this->throws(ParserException::class, 'Syntax error');

		$lexer = new QueryLexer();
		$lexer->tokens('field.h* = "test"');
	}

	public function testInvalidSpecialChar3(): void
	{
		$this->throws(ParserException::class, 'Invalid use of special');

		$lexer = new QueryLexer();
		$lexer->tokens('field.?h = "test"');
	}

	public function testInvalidSpecialChar4(): void
	{
		$this->throws(ParserException::class, 'Syntax error');

		$lexer = new QueryLexer();
		$lexer->tokens('field.h? = "test"');
	}

	public function testInvalidSpecialChar5(): void
	{
		$this->throws(ParserException::class, 'Syntax error');

		$lexer = new QueryLexer();
		$lexer->tokens('fiel?d = "test"');
	}

	public function testUnterminatedString(): void
	{
		$this->throws(ParserException::class, 'Unterminated string');

		$lexer = new QueryLexer();
		$lexer->tokens('field = "test');
	}

	public function testInvalidOperator(): void
	{
		$this->throws(ParserException::class, 'Invalid operator');

		$lexer = new QueryLexer();
		$lexer->tokens('field !- test');
	}

	public function testSyntaxError(): void
	{
		$this->throws(ParserException::class, 'Syntax error');

		$lexer = new QueryLexer();
		$lexer->tokens('field # test');
	}

	public function testInvalidNumber(): void
	{
		$this->throws(ParserException::class, 'Invalid number');

		$lexer = new QueryLexer();
		$lexer->tokens('field = 10.');
	}

	public function testSyntaxErrorSpecialCaseMinus(): void
	{
		$this->throws(ParserException::class, 'Syntax error');

		// We need to test minus separately as a minus starts
		// the number parser.
		$lexer = new QueryLexer();
		$lexer->tokens('field - test');
	}

	public function testAndWithGroupedOrQuery(): void
	{
		$lexer = new QueryLexer();
		$tokens = $lexer->tokens('field = "test" & (name.de = "test" | name.en = "test") ');

		$this->assertSame('Field', $tokens[0]->type->name);
		$this->assertSame('Equal', $tokens[1]->type->name);
		$this->assertSame('String', $tokens[2]->type->name);

		$this->assertSame('And', $tokens[3]->type->name);

		$this->assertSame('LeftParen', $tokens[4]->type->name);

		$this->assertSame('Field', $tokens[5]->type->name);
		$this->assertSame('Equal', $tokens[6]->type->name);
		$this->assertSame('String', $tokens[7]->type->name);

		$this->assertSame('Or', $tokens[8]->type->name);

		$this->assertSame('Field', $tokens[9]->type->name);
		$this->assertSame('Equal', $tokens[10]->type->name);
		$this->assertSame('String', $tokens[11]->type->name);

		$this->assertSame('RightParen', $tokens[12]->type->name);
	}

	public function testMoreNesting(): void
	{
		$lexer = new QueryLexer();
		$tokens = $lexer->tokens('(field = "test" & ((name.de = "test") | name.en = "test"))');

		$this->assertSame('LeftParen', $tokens[0]->type->name);

		$this->assertSame('Field', $tokens[1]->type->name);
		$this->assertSame('Equal', $tokens[2]->type->name);
		$this->assertSame('String', $tokens[3]->type->name);

		$this->assertSame('And', $tokens[4]->type->name);

		$this->assertSame('LeftParen', $tokens[5]->type->name);
		$this->assertSame('LeftParen', $tokens[6]->type->name);

		$this->assertSame('Field', $tokens[7]->type->name);
		$this->assertSame('Equal', $tokens[8]->type->name);
		$this->assertSame('String', $tokens[9]->type->name);
		$this->assertSame('RightParen', $tokens[10]->type->name);

		$this->assertSame('Or', $tokens[11]->type->name);

		$this->assertSame('Field', $tokens[12]->type->name);
		$this->assertSame('Equal', $tokens[13]->type->name);
		$this->assertSame('String', $tokens[14]->type->name);

		$this->assertSame('RightParen', $tokens[15]->type->name);
		$this->assertSame('RightParen', $tokens[16]->type->name);
	}

	public function testTokenGroups(): void
	{
		$lexer = new QueryLexer(['builtin1', 'builtin2', 'builtin3']);
		$tokens = $lexer->tokens(QUERY_ALL_ELEMENTS);

		$this->assertSame('LeftParen', $tokens[0]->group->name);
		$this->assertSame('Operand', $tokens[1]->group->name);
		$this->assertSame('Operator', $tokens[2]->group->name);
		$this->assertSame('Operand', $tokens[3]->group->name);
		$this->assertSame('BooleanOperator', $tokens[4]->group->name);
		$this->assertSame('Operand', $tokens[5]->group->name);
		$this->assertSame('Operator', $tokens[6]->group->name);
		$this->assertSame('Operand', $tokens[7]->group->name);
		$this->assertSame('BooleanOperator', $tokens[8]->group->name);
		$this->assertSame('Operand', $tokens[9]->group->name);
		$this->assertSame('Operator', $tokens[10]->group->name);
		$this->assertSame('Operand', $tokens[11]->group->name);
		$this->assertSame('BooleanOperator', $tokens[12]->group->name);
		$this->assertSame('Operand', $tokens[13]->group->name);
		$this->assertSame('Operator', $tokens[14]->group->name);
		$this->assertSame('Operand', $tokens[15]->group->name);
		$this->assertSame('RightParen', $tokens[16]->group->name);
		$this->assertSame('BooleanOperator', $tokens[17]->group->name);
		$this->assertSame('LeftParen', $tokens[18]->group->name);
		$this->assertSame('Operand', $tokens[19]->group->name);
		$this->assertSame('Operator', $tokens[20]->group->name);
		$this->assertSame('Operand', $tokens[21]->group->name);
		$this->assertSame('BooleanOperator', $tokens[22]->group->name);
		$this->assertSame('Operand', $tokens[23]->group->name);
		$this->assertSame('Operator', $tokens[24]->group->name);
		$this->assertSame('Operand', $tokens[25]->group->name);
		$this->assertSame('BooleanOperator', $tokens[26]->group->name);
		$this->assertSame('Operand', $tokens[27]->group->name);
		$this->assertSame('Operator', $tokens[28]->group->name);
		$this->assertSame('Operand', $tokens[29]->group->name);
		$this->assertSame('BooleanOperator', $tokens[30]->group->name);
		$this->assertSame('Operand', $tokens[31]->group->name);
		$this->assertSame('Operator', $tokens[32]->group->name);
		$this->assertSame('Operand', $tokens[33]->group->name);
		$this->assertSame('BooleanOperator', $tokens[34]->group->name);
		$this->assertSame('Operand', $tokens[35]->group->name);
		$this->assertSame('Operator', $tokens[36]->group->name);
		$this->assertSame('Operand', $tokens[37]->group->name);
		$this->assertSame('BooleanOperator', $tokens[38]->group->name);
		$this->assertSame('Operand', $tokens[39]->group->name);
		$this->assertSame('Operator', $tokens[40]->group->name);
		$this->assertSame('Operand', $tokens[41]->group->name);
		$this->assertSame('BooleanOperator', $tokens[42]->group->name);
		$this->assertSame('Operand', $tokens[43]->group->name);
		$this->assertSame('Operator', $tokens[44]->group->name);
		$this->assertSame('Operand', $tokens[45]->group->name);
		$this->assertSame('BooleanOperator', $tokens[46]->group->name);
		$this->assertSame('Operand', $tokens[47]->group->name);
		$this->assertSame('Operator', $tokens[48]->group->name);
		$this->assertSame('Operand', $tokens[49]->group->name);
		$this->assertSame('BooleanOperator', $tokens[50]->group->name);
		$this->assertSame('Operand', $tokens[51]->group->name);
		$this->assertSame('Operator', $tokens[52]->group->name);
		$this->assertSame('Operand', $tokens[53]->group->name);
		$this->assertSame('BooleanOperator', $tokens[54]->group->name);
		$this->assertSame('Operand', $tokens[55]->group->name);
		$this->assertSame('Operator', $tokens[56]->group->name);
		$this->assertSame('Operand', $tokens[57]->group->name);
		$this->assertSame('RightParen', $tokens[58]->group->name);
	}

	public function testTokenTypes(): void
	{
		$lexer = new QueryLexer(['builtin1', 'builtin2', 'builtin3']);
		$tokens = $lexer->tokens(QUERY_ALL_ELEMENTS);

		$this->assertSame('LeftParen', $tokens[0]->type->name);
		$this->assertSame('Boolean', $tokens[1]->type->name);
		$this->assertSame('Equal', $tokens[2]->type->name);
		$this->assertSame('Field', $tokens[3]->type->name);
		$this->assertSame('And', $tokens[4]->type->name);
		$this->assertSame('Builtin', $tokens[5]->type->name);
		$this->assertSame('Greater', $tokens[6]->type->name);
		$this->assertSame('Keyword', $tokens[7]->type->name);
		$this->assertSame('And', $tokens[8]->type->name);
		$this->assertSame('Null', $tokens[9]->type->name);
		$this->assertSame('GreaterEqual', $tokens[10]->type->name);
		$this->assertSame('Number', $tokens[11]->type->name);
		$this->assertSame('And', $tokens[12]->type->name);
		$this->assertSame('Field', $tokens[13]->type->name);
		$this->assertSame('Less', $tokens[14]->type->name);
		$this->assertSame('String', $tokens[15]->type->name);
		$this->assertSame('RightParen', $tokens[16]->type->name);
		$this->assertSame('Or', $tokens[17]->type->name);
		$this->assertSame('LeftParen', $tokens[18]->type->name);
		$this->assertSame('Number', $tokens[19]->type->name);
		$this->assertSame('LessEqual', $tokens[20]->type->name);
		$this->assertSame('Builtin', $tokens[21]->type->name);
		$this->assertSame('Or', $tokens[22]->type->name);
		$this->assertSame('Field', $tokens[23]->type->name);
		$this->assertSame('Regex', $tokens[24]->type->name);
		$this->assertSame('String', $tokens[25]->type->name);
		$this->assertSame('Or', $tokens[26]->type->name);
		$this->assertSame('Builtin', $tokens[27]->type->name);
		$this->assertSame('NotRegex', $tokens[28]->type->name);
		$this->assertSame('String', $tokens[29]->type->name);
		$this->assertSame('Or', $tokens[30]->type->name);
		$this->assertSame('Path', $tokens[31]->type->name);
		$this->assertSame('Unequal', $tokens[32]->type->name);
		$this->assertSame('Number', $tokens[33]->type->name);
		$this->assertSame('Or', $tokens[34]->type->name);
		$this->assertSame('Path', $tokens[35]->type->name);
		$this->assertSame('Unlike', $tokens[36]->type->name);
		$this->assertSame('String', $tokens[37]->type->name);
		$this->assertSame('And', $tokens[38]->type->name);
		$this->assertSame('Field', $tokens[39]->type->name);
		$this->assertSame('Like', $tokens[40]->type->name);
		$this->assertSame('String', $tokens[41]->type->name);
		$this->assertSame('And', $tokens[42]->type->name);
		$this->assertSame('Field', $tokens[43]->type->name);
		$this->assertSame('IRegex', $tokens[44]->type->name);
		$this->assertSame('String', $tokens[45]->type->name);
		$this->assertSame('And', $tokens[46]->type->name);
		$this->assertSame('Field', $tokens[47]->type->name);
		$this->assertSame('INotRegex', $tokens[48]->type->name);
		$this->assertSame('String', $tokens[49]->type->name);
		$this->assertSame('Or', $tokens[50]->type->name);
		$this->assertSame('Field', $tokens[51]->type->name);
		$this->assertSame('ILike', $tokens[52]->type->name);
		$this->assertSame('String', $tokens[53]->type->name);
		$this->assertSame('Or', $tokens[54]->type->name);
		$this->assertSame('Field', $tokens[55]->type->name);
		$this->assertSame('IUnlike', $tokens[56]->type->name);
		$this->assertSame('String', $tokens[57]->type->name);
		$this->assertSame('RightParen', $tokens[58]->type->name);
	}
}
