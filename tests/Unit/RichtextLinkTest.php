<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Richtext\Link;
use Cosray\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * @internal
 *
 * @coversNothing
 */
final class RichtextLinkTest extends TestCase
{
	/** @return iterable<string, array{string, bool}> */
	public static function urls(): iterable
	{
		$cases = json_decode((string) file_get_contents(__DIR__ . '/../../contract/link-urls.json'), true);

		foreach ($cases as $case) {
			yield json_encode($case['url']) => [$case['url'], $case['safe']];
		}
	}

	#[DataProvider('urls')]
	public function testFollowsTheSharedRule(string $url, bool $safe): void
	{
		$this->assertSame($safe, Link::safe($url));
	}
}
