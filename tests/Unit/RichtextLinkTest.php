<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Assets\Asset;
use Cosray\Richtext\Link;
use Cosray\Richtext\Resolver;
use Cosray\Tests\TestCase;
use Override;
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

	public function testResolvesAPageForTheCurrentLocale(): void
	{
		$resolver = new class implements Resolver {
			#[Override]
			public function asset(string $uid): ?Asset
			{
				return null;
			}

			#[Override]
			public function nodePath(string $uid): ?string
			{
				return $uid === 'n1' ? '/de/seite' : null;
			}

			#[Override]
			public function localize(array $map): mixed
			{
				return null;
			}
		};

		$this->assertSame(['href' => '/de/seite'], Link::attributes(['node' => 'n1'], $resolver));
		$this->assertNull(Link::attributes(['node' => 'gone'], $resolver));
	}

	#[DataProvider('urls')]
	public function testFollowsTheSharedRule(string $url, bool $safe): void
	{
		$this->assertSame($safe, Link::safe($url));
	}
}
