<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Api\PreviewToken;
use Cosray\Tests\TestCase;

final class PreviewTokenTest extends TestCase
{
	public function testATokenGrantsItsNodeUntilItExpires(): void
	{
		$tokens = new PreviewToken('secret');
		$token = $tokens->issue('page-uid', expires: 2000);

		$this->assertSame('page-uid', $tokens->uid($token, now: 1999));
		$this->assertSame('page-uid', $tokens->uid($token, now: 2000));
		$this->assertNull($tokens->uid($token, now: 2001));
	}

	public function testForgedOrMalformedTokensGrantNothing(): void
	{
		$tokens = new PreviewToken('secret');
		$token = $tokens->issue('page-uid');
		[$payload, $signature] = explode('.', $token);
		$forged = rtrim(strtr(base64_encode('{"uid":"other","exp":9999999999}'), '+/', '-_'), '=') . '.' . $signature;

		$this->assertNull(new PreviewToken('other secret')->uid($token));
		$this->assertNull($tokens->uid($forged));
		$this->assertNull($tokens->uid($payload));
		$this->assertNull($tokens->uid('a.b.c'));
		$this->assertNull($tokens->uid(''));
	}

	public function testSigningNeedsTheAppSecret(): void
	{
		$this->assertNull(PreviewToken::fromConfig($this->config(['app.secret' => null])));
		$this->assertInstanceOf(PreviewToken::class, PreviewToken::fromConfig($this->config(['app.secret' => 'x'])));
	}
}
