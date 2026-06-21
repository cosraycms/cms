<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Tests\TestCase;
use Cosray\View\Boiler\Renderer as BoilerRenderer;

/**
 * @internal
 *
 * @coversNothing
 */
final class PanelNodeViewTest extends TestCase
{
	public function testNodeFormTargetsMainPanelWhenBoosted(): void
	{
		$renderer = new BoilerRenderer(self::root() . '/panel/views');

		$html = $renderer->render('node', [
			'boosted' => true,
			'panelPath' => '/cp',
			'uid' => 'station',
			'title' => 'Station',
			'published' => true,
			'hidden' => false,
			'locked' => false,
			'saved' => false,
			'fields' => [],
		]);

		$this->assertStringContainsString('class="node-form"', $html);
		$this->assertStringContainsString('hx-boost="true"', $html);
		$this->assertStringContainsString('hx-target="#main"', $html);
	}
}
