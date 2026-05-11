<?php

declare(strict_types=1);

namespace Celemas\Cms\Tests\Unit;

use Celemas\Cms\Tests\TestCase;
use Celemas\Cms\Token;

final class TokenTest extends TestCase
{
	public function testTokenGeneration(): void
	{
		$secret = 'test-secret-key';
		$token = new Token($secret);

		$tokenValue = $token->get();
		$this->assertSame(32, strlen($tokenValue)); // 16 bytes = 32 hex chars
		$this->assertTrue(ctype_xdigit($tokenValue));
	}

	public function testTokenWithProvidedValue(): void
	{
		$secret = 'test-secret-key';
		$providedToken = 'abcdef1234567890abcdef1234567890';
		$token = new Token($secret, $providedToken);

		$this->assertSame($providedToken, $token->get());
	}

	public function testTokenHash(): void
	{
		$secret = 'test-secret-key';
		$providedToken = 'abcdef1234567890abcdef1234567890';
		$token = new Token($secret, $providedToken);

		$hash = $token->hash();
		$this->assertSame(64, strlen($hash)); // SHA256 = 64 hex chars
		$this->assertTrue(ctype_xdigit($hash));

		// Verify it's a valid HMAC-SHA256 hash
		$expectedHash = hash_hmac('sha256', $providedToken, $secret);
		$this->assertSame($expectedHash, $hash);
	}

	public function testDifferentSecretsProduceDifferentHashes(): void
	{
		$tokenValue = 'abcdef1234567890abcdef1234567890';
		$token1 = new Token('secret-one', $tokenValue);
		$token2 = new Token('secret-two', $tokenValue);

		$this->assertNotSame($token1->hash(), $token2->hash());
	}

	public function testDifferentTokensProduceDifferentHashes(): void
	{
		$secret = 'test-secret-key';
		$token1 = new Token($secret, 'token-one-abcdef1234567890ab');
		$token2 = new Token($secret, 'token-two-abcdef1234567890ab');

		$this->assertNotSame($token1->hash(), $token2->hash());
	}
}
