<?php

declare(strict_types=1);

namespace Celemas\Cms\Tests\Unit;

use Celemas\Cms\Tests\TestCase;
use Celemas\Cms\Util\Number;
use Exception;

final class NumberTest extends TestCase
{
	public function testParseDecimalEuropean(): void
	{
		$this->assertSame('1234.56', Number::parseDecimal('1.234,56'));
		$this->assertSame('1234.56', Number::parseDecimal('1234,56'));
		$this->assertSame('1234567.89', Number::parseDecimal('1.234.567,89'));
	}

	public function testParseDecimalAmerican(): void
	{
		$this->assertSame('1234.56', Number::parseDecimal('1,234.56'));
		$this->assertSame('1234.56', Number::parseDecimal('1234.56'));
		$this->assertSame('1234567.89', Number::parseDecimal('1,234,567.89'));
	}

	public function testParseDecimalSimple(): void
	{
		$this->assertSame('123', Number::parseDecimal('123'));
		$this->assertSame('123.45', Number::parseDecimal('123.45'));
		$this->assertSame('0.5', Number::parseDecimal('0,5'));
	}

	public function testParseDecimalWithSpaces(): void
	{
		$this->assertSame('1234.56', Number::parseDecimal('1 234,56'));
		$this->assertSame('1234.56', Number::parseDecimal(' 1234.56 '));
		$this->assertSame('1234567.89', Number::parseDecimal('1 234 567.89'));
	}

	public function testParseDecimalInvalid(): void
	{
		$this->throws(Exception::class, 'This is not a valid number');
		Number::parseDecimal('abc');
	}

	public function testParseDecimalInvalidWithSymbols(): void
	{
		$this->throws(Exception::class, 'This is not a valid number');
		Number::parseDecimal('123-456');
	}
}
