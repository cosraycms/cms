<?php

declare(strict_types=1);

namespace Celemas\Cms\Tests\Unit\Boiler;

use Celemas\Boiler\Exception\LookupException;
use Celemas\Cms\Boiler\Renderer;
use Celemas\Cms\Boiler\RendererException;
use Celemas\Cms\Tests\Fixtures\Boiler\Whitelisted;
use Celemas\Cms\Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class RendererTest extends TestCase
{
	public function testHtmlArrayOfTemplateDirs(): void
	{
		$renderer = new Renderer(
			$this->templates(),
			['config' => $this->config(['app.name' => 'boiler'])],
			[],
			true,
		);
		$result = $renderer->render('renderer', ['text' => 'numbers', 'arr' => [1, 2, 3]]);

		$this->assertSame("<h1>boiler</h1>\n<p>numbers</p><p>1</p><p>2</p><p>3</p>", $result);
	}

	public function testHtmlTemplateDirAsString(): void
	{
		$renderer = new Renderer(
			$this->templates()[0],
			['config' => $this->config(['app.name' => 'boiler'])],
			[],
			true,
		);
		$result = $renderer->render('renderer', ['text' => 'numbers', 'arr' => [1, 2, 3]]);

		$this->assertSame("<h1>boiler</h1>\n<p>numbers</p><p>1</p><p>2</p><p>3</p>", $result);
	}

	public function testTrustedClasses(): void
	{
		$renderer = new Renderer(
			$this->templates(),
			[],
			[Whitelisted::class],
			true,
		);
		$result = $renderer->render('whitelist', ['wl' => new Whitelisted(), 'content' => 'test']);

		$this->assertSame('<h1>headline</h1><p>test</p>', $result);
	}

	public function testContentType(): void
	{
		$renderer = new Renderer($this->templates());
		$this->assertSame('text/html', $renderer->contentType());

		$renderer = new Renderer($this->templates(), contentType: 'text/xhtml');
		$this->assertSame('text/xhtml', $renderer->contentType());
	}

	public function testTemplateMissing(): void
	{
		$this->throws(LookupException::class);

		new Renderer($this->templates())->render('missing', []);
	}

	public function testTemplateDirsMissing(): void
	{
		$this->throws(RendererException::class);

		new Renderer([])->render('renderer', []);
	}

	private function templates(): array
	{
		return [self::root() . '/tests/Fixtures/Boiler/templates'];
	}
}
