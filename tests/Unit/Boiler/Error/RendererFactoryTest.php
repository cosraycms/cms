<?php

declare(strict_types=1);

namespace Celemas\Cms\Tests\Unit\Boiler\Error;

use Celemas\Cms\Boiler\Error\Renderer;
use Celemas\Cms\Boiler\Error\RendererFactory;
use Celemas\Cms\Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class RendererFactoryTest extends TestCase
{
	public function testWithTemplateCreatesRenderer(): void
	{
		$factory = new RendererFactory(
			dirs: [self::root() . '/tests/Fixtures/Boiler/templates'],
			context: ['foo' => 'bar'],
			trusted: [],
			autoescape: true,
		);

		$renderer = $factory->withTemplate('error');

		$this->assertInstanceOf(Renderer::class, $renderer);
	}
}
