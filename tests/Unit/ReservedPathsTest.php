<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Celema\Core\Exception\HttpBadRequest;
use Cosray\Node\ReservedPaths;
use Cosray\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * @internal
 *
 * @coversNothing
 */
final class ReservedPathsTest extends TestCase
{
	/** @return iterable<string, array{string, ?string}> */
	public static function paths(): iterable
	{
		yield 'panel root' => ['/panel', '/panel'];
		yield 'below the panel' => ['/panel/about', '/panel'];
		yield 'without leading slash' => ['panel/about', '/panel'];
		yield 'originals' => ['/assets/logo.svg', '/assets'];
		yield 'renditions' => ['/cache/thumb', '/cache'];
		yield 'previews' => ['/preview/abc', '/preview'];
		yield 'same start, other segment' => ['/panelists', null];
		yield 'nested elsewhere' => ['/about/panel', null];
		yield 'root' => ['/', null];
	}

	#[DataProvider('paths')]
	public function testMatchesDefaultPrefixesBySegment(string $path, ?string $prefix): void
	{
		$reserved = ReservedPaths::fromConfig($this->config());

		$this->assertSame($prefix, $reserved->match($path));
	}

	public function testFollowsConfiguredPrefixes(): void
	{
		$reserved = ReservedPaths::fromConfig($this->config([
			'panel.path' => '/admin/',
			'path.assets' => '/files',
		]));

		$this->assertSame('/admin', $reserved->match('/admin/users'));
		$this->assertSame('/files', $reserved->match('/files'));
		$this->assertNull($reserved->match('/panel'));
		$this->assertNull($reserved->match('/assets/logo.svg'));
	}

	public function testRootPrefixReservesNothing(): void
	{
		$this->assertNull(new ReservedPaths(['/'])->match('/about'));
	}

	public function testRefusesAReservedPathAmongFreeOnes(): void
	{
		$this->expectException(HttpBadRequest::class);

		ReservedPaths::fromConfig($this->config())->assertFree(['en' => '/about', 'de' => ' panel/ueber ']);
	}

	public function testAcceptsFreeAndEmptyPaths(): void
	{
		ReservedPaths::fromConfig($this->config())->assertFree(['en' => '/about', 'de' => '', 'fr' => null]);

		$this->addToAssertionCount(1);
	}
}
