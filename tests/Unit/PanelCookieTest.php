<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Panel\Cookie;
use Cosray\Tests\TestCase;

final class PanelCookieTest extends TestCase
{
	public function testReadsEveryKnownChoice(): void
	{
		$cookie = Cookie::parse('inspector=collapsed&blocks=wide&theme=dark');

		$this->assertTrue($cookie->is('inspector', 'collapsed'));
		$this->assertTrue($cookie->is('blocks', 'wide'));
		$this->assertSame('dark', $cookie->choice('theme'));
	}

	public function testIgnoresWhatThePanelDoesNotWrite(): void
	{
		$cookie = Cookie::parse('inspector=open&blocks&theme=sepia&layout=grid&=wide');

		$this->assertFalse($cookie->is('inspector', 'open'));
		$this->assertFalse($cookie->is('blocks', ''));
		$this->assertNull($cookie->choice('theme'));
		$this->assertNull($cookie->choice('layout'));
	}
}
