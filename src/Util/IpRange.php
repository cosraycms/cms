<?php

declare(strict_types=1);

namespace Cosray\Util;

/**
 * Single IP addresses and CIDR ranges like `10.0.0.0/8` or `fd00::/8`,
 * IPv4 and IPv6 alike.
 *
 * @internal
 */
final class IpRange
{
	public static function valid(string $range): bool
	{
		return self::parse($range) !== null;
	}

	public static function contains(string $range, string $ip): bool
	{
		$parsed = self::parse($range);
		$address = filter_var($ip, FILTER_VALIDATE_IP) === false ? false : inet_pton($ip);

		if ($parsed === null || $address === false || strlen($address) !== strlen($parsed[0])) {
			return false;
		}

		[$network, $bits] = $parsed;
		$bytes = intdiv($bits, 8);

		if (substr($network, 0, $bytes) !== substr($address, 0, $bytes)) {
			return false;
		}

		$rest = $bits % 8;

		if ($rest === 0) {
			return true;
		}

		$mask = (0xFF << (8 - $rest)) & 0xFF;

		return (ord($network[$bytes]) & $mask) === (ord($address[$bytes]) & $mask);
	}

	/** @return ?array{string, int} the network in binary and its prefix length */
	private static function parse(string $range): ?array
	{
		$slash = strpos($range, '/');
		$ip = $slash === false ? $range : substr($range, 0, $slash);
		$bits = $slash === false ? null : substr($range, $slash + 1);
		$network = filter_var($ip, FILTER_VALIDATE_IP) === false ? false : inet_pton($ip);

		if ($network === false) {
			return null;
		}

		$max = strlen($network) * 8;

		if ($bits === null) {
			return [$network, $max];
		}

		if (preg_match('/^\d{1,3}$/', $bits) !== 1 || (int) $bits > $max) {
			return null;
		}

		return [$network, (int) $bits];
	}
}
