<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit\View\Boiler\Error;

use Cosray\Tests\TestCase;
use Cosray\View\Boiler\Error\Renderer;
use Cosray\View\Boiler\Error\RendererFactory;

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
