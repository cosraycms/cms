<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Tests\TestCase;

/**
 * PHP half of the shared form-name parsing semantics: parse_str() is the
 * reference implementation the panel's JSON transport encoder
 * (panel/src/lib/form-json.ts) must match, so both save transports hand
 * the server identical form data. The JS half is
 * panel/tests/contract/form-names.test.ts.
 *
 * @internal
 *
 * @coversNothing
 */
final class FormNameContractTest extends TestCase
{
	public function testContractFixtures(): void
	{
		$fixtures = json_decode(
			(string) file_get_contents(__DIR__ . '/../../contract/form-names.json'),
			true,
		);
		$this->assertNotEmpty($fixtures['cases']);

		foreach ($fixtures['cases'] as $case) {
			$pairs = array_map(
				static fn(array $entry): string => urlencode((string) $entry[0]) . '=' . urlencode((string) $entry[1]),
				$case['entries'],
			);
			parse_str(implode('&', $pairs), $parsed);

			$this->assertSame($case['tree'], $parsed, "contract case: {$case['name']}");
		}
	}
}
