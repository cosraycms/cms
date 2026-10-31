<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Panel\Cookie;
use Cosray\Tests\TestCase;

final class PanelCookieTest extends TestCase
{
	public function testReadsEveryKnownChoice(): void
	{
		$cookie = Cookie::parse('inspector=collapsed&blocks=wide');

		$this->assertTrue($cookie->is('inspector', 'collapsed'));
		$this->assertTrue($cookie->is('blocks', 'wide'));
	}

	public function testIgnoresWhatThePanelDoesNotWrite(): void
	{
		$cookie = Cookie::parse('inspector=open&blocks&theme=dark&=wide');

		$this->assertFalse($cookie->is('inspector', 'open'));
		$this->assertFalse($cookie->is('blocks', ''));
		$this->assertFalse($cookie->is('theme', 'dark'));
	}
}
