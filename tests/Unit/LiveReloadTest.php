<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Cms;
use Cosray\Context;
use Cosray\Tests\TestCase;
use Cosray\View\Boiler\Renderer;

/**
 * @internal
 *
 * @coversNothing
 */
final class LiveReloadTest extends TestCase
{
	protected function tearDown(): void
	{
		putenv('CELEMA_LIVE_RELOAD');

		parent::tearDown();
	}

	public function testRendersTheScriptWhileTheDevServerWatches(): void
	{
		putenv('CELEMA_LIVE_RELOAD=http://localhost:19830/celema-live-reload.js?a=1&b=2');

		$this->assertSame(
			'<body><script src="http://localhost:19830/celema-live-reload.js?a=1&amp;b=2" defer></script></body>',
			$this->render(),
		);
	}

	public function testRendersNothingOtherwise(): void
	{
		putenv('CELEMA_LIVE_RELOAD');

		$this->assertSame('<body></body>', $this->render());
	}

	private function render(): string
	{
		$cms = new Cms(
			new Context($this->db(), null, $this->config(), $this->container(), $this->factory()),
			self::blockServices(),
		);
		$renderer = new Renderer(
			self::root() . '/tests/Fixtures/Boiler/templates',
			trusted: [Cms::class],
		);

		return trim($renderer->render('live-reload', ['cms' => $cms]));
	}
}
