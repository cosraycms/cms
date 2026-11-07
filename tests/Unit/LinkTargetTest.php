<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Assets\Asset;
use Cosray\LinkTarget;
use Cosray\References\Resolver;
use Cosray\Tests\TestCase;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * @internal
 *
 * @coversNothing
 */
final class LinkTargetTest extends TestCase
{
	/** @return iterable<string, array{string, bool}> */
	public static function urls(): iterable
	{
		$cases = json_decode((string) file_get_contents(__DIR__ . '/../../contract/link-urls.json'), true);

		foreach ($cases as $case) {
			yield json_encode($case['url']) => [$case['url'], $case['safe']];
		}
	}

	public function testOffersTheSharedRelValues(): void
	{
		$rels = json_decode((string) file_get_contents(__DIR__ . '/../../contract/link-rels.json'), true);

		$this->assertSame($rels, LinkTarget::RELS);
	}

	public function testAddsOnlyWhatTheEditorMayChooseToTheRel(): void
	{
		$attributes = LinkTarget::attributes(
			['href' => '/a', 'rel' => 'sponsored nofollow "><script> noreferrer'],
			$this->resolver(),
		);

		$this->assertSame('noopener nofollow sponsored noreferrer', $attributes['rel'] ?? null);
		$this->assertSame('noopener', LinkTarget::attributes(['href' => '/a'], $this->resolver())['rel'] ?? null);
		$this->assertSame(
			['href' => '/p'],
			LinkTarget::attributes(['node' => 'n1', 'rel' => 'nofollow'], $this->resolver()),
		);
	}

	public function testChecksAStoredRel(): void
	{
		$this->assertTrue(LinkTarget::valid(['href' => '/a', 'rel' => 'nofollow sponsored']));
		$this->assertTrue(LinkTarget::valid(['href' => '/a', 'rel' => null]));
		$this->assertFalse(LinkTarget::valid(['href' => '/a', 'rel' => 'nofollow noopener']));
		$this->assertFalse(LinkTarget::valid(['href' => '/a', 'rel' => ['nofollow']]));
	}

	public function testResolvesAPageForTheCurrentLocale(): void
	{
		$resolver = $this->resolver();

		$this->assertSame(['href' => '/p'], LinkTarget::attributes(['node' => 'n1'], $resolver));
		$this->assertNull(LinkTarget::attributes(['node' => 'gone'], $resolver));
	}

	#[DataProvider('urls')]
	public function testFollowsTheSharedRule(string $url, bool $safe): void
	{
		$this->assertSame($safe, LinkTarget::safe($url));
	}

	private function resolver(): Resolver
	{
		return new class implements Resolver {
			#[Override]
			public function asset(string $uid): ?Asset
			{
				return null;
			}

			#[Override]
			public function nodePath(string $uid): ?string
			{
				return $uid === 'n1' ? '/p' : null;
			}

			#[Override]
			public function localize(array $map): mixed
			{
				return null;
			}
		};
	}
}
