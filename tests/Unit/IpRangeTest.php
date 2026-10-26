<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Tests\TestCase;
use Cosray\Util\IpRange;

final class IpRangeTest extends TestCase
{
	public function testAddressesAndCidrRangesOfBothFamiliesAreValid(): void
	{
		foreach (['192.0.2.1', '10.0.0.0/8', '0.0.0.0/0', '2001:db8::1', 'fd00::/8', '::/128'] as $range) {
			$this->assertTrue(IpRange::valid($range), $range);
		}

		foreach ([
			'',
			'proxy',
			'10.0.0.0/',
			'10.0.0.0/33',
			'10.0.0.0/-1',
			'10.0.0.0/8/8',
			'::/129',
			'10.0.0/8',
		] as $range) {
			$this->assertFalse(IpRange::valid($range), $range);
		}
	}

	public function testAnAddressContainsOnlyItself(): void
	{
		$this->assertTrue(IpRange::contains('192.0.2.1', '192.0.2.1'));
		$this->assertFalse(IpRange::contains('192.0.2.1', '192.0.2.2'));
		$this->assertTrue(IpRange::contains('2001:db8::1', '2001:0db8:0000::1'));
	}

	public function testARangeContainsTheAddressesOfItsPrefix(): void
	{
		$this->assertTrue(IpRange::contains('10.0.0.0/8', '10.255.0.1'));
		$this->assertFalse(IpRange::contains('10.0.0.0/8', '11.0.0.1'));
		$this->assertTrue(IpRange::contains('172.16.0.0/12', '172.31.255.255'));
		$this->assertFalse(IpRange::contains('172.16.0.0/12', '172.32.0.1'));
		$this->assertTrue(IpRange::contains('0.0.0.0/0', '203.0.113.9'));
		$this->assertTrue(IpRange::contains('fd00::/8', 'fd12:3456::1'));
		$this->assertFalse(IpRange::contains('fd00::/8', 'fe80::1'));
	}

	public function testOtherFamiliesAndInvalidInputMatchNothing(): void
	{
		$this->assertFalse(IpRange::contains('0.0.0.0/0', '::1'));
		$this->assertFalse(IpRange::contains('::/0', '127.0.0.1'));
		$this->assertFalse(IpRange::contains('10.0.0.0/8', 'unknown'));
		$this->assertFalse(IpRange::contains('proxy', '10.0.0.1'));
	}
}
