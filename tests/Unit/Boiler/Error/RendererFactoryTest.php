<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit\Boiler\Error;

use Cosray\Boiler\Error\Renderer;
use Cosray\Boiler\Error\RendererFactory;
use Cosray\Tests\TestCase;

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
