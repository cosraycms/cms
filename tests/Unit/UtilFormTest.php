<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Celema\Core\Request;
use Cosray\Tests\TestCase;
use Cosray\Util\Form;

/**
 * @internal
 *
 * @covers \Cosray\Util\Form
 */
final class UtilFormTest extends TestCase
{
	public function testParsedBodyIsUsedWhenPresent(): void
	{
		$request = new Request($this->psrRequest()->withParsedBody(['from' => 'parsed']));

		$this->assertSame(['from' => 'parsed'], Form::body($request));
	}

	public function testJsonBodyIsDecoded(): void
	{
		$request = $this->rawRequest('application/json', '{"from":"json"}');

		$this->assertSame(['from' => 'json'], Form::body($request));
	}

	public function testUrlencodedBodyIsParsed(): void
	{
		// The PUT case: PHP leaves the parsed body empty here.
		$request = $this->rawRequest('application/x-www-form-urlencoded', 'from=urlencoded&n=2');

		$this->assertSame(['from' => 'urlencoded', 'n' => '2'], Form::body($request));
	}

	public function testContentTypeParametersAreIgnored(): void
	{
		$request = $this->rawRequest('application/json; charset=utf-8', '{"from":"json"}');

		$this->assertSame(['from' => 'json'], Form::body($request));
	}

	public function testScalarJsonBodyYieldsAnEmptyArray(): void
	{
		$request = $this->rawRequest('application/json', '"just a string"');

		$this->assertSame([], Form::body($request));
	}

	public function testUnknownContentTypeYieldsAnEmptyArray(): void
	{
		$request = $this->rawRequest('text/plain', 'from=ignored');

		$this->assertSame([], Form::body($request));
	}

	private function rawRequest(string $contentType, string $body): Request
	{
		$psr = $this
			->psrRequest()
			->withHeader('Content-Type', $contentType)
			->withBody($this->factory()->streamFactory()->createStream($body));

		return new Request($psr);
	}
}
