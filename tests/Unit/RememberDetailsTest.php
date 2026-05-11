<?php

declare(strict_types=1);

namespace Celemas\Cms\Tests\Unit;

use Celemas\Cms\RememberDetails;
use Celemas\Cms\Tests\TestCase;
use Celemas\Cms\Token;

final class RememberDetailsTest extends TestCase
{
	public function testConstructorSetsProperties(): void
	{
		$secret = 'test-secret';
		$tokenValue = 'test-token-value';
		$token = new Token($secret, $tokenValue);
		$expires = 1_704_067_200;

		$details = new RememberDetails($token, $expires);

		$this->assertSame($token, $details->token);
		$this->assertSame($expires, $details->expires);
	}

	public function testPropertiesAreReadonly(): void
	{
		$secret = 'test-secret';
		$token = new Token($secret, 'test-token');
		$expires = 1_704_067_200;

		$details = new RememberDetails($token, $expires);

		// Verify properties are accessible
		$this->assertInstanceOf(Token::class, $details->token);
		$this->assertSame(1_704_067_200, $details->expires);
	}
}
