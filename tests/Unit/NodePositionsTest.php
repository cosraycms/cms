<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Exception\RuntimeException;
use Cosray\Node\Positions;
use Cosray\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class NodePositionsTest extends TestCase
{
	public static function moves(): iterable
	{
		yield 'before the first' => ['d', 'a', false, ['d', 'a', 'b', 'c']];
		yield 'after the last' => ['a', 'd', true, ['b', 'c', 'd', 'a']];
		yield 'down past one' => ['b', 'c', true, ['a', 'c', 'b', 'd']];
		yield 'up past one' => ['c', 'b', false, ['a', 'c', 'b', 'd']];
		yield 'already there' => ['b', 'a', true, ['a', 'b', 'c', 'd']];
	}

	#[DataProvider('moves')]
	public function testMovesNextToTheNeighbour(string $uid, string $neighbour, bool $after, array $expected): void
	{
		$this->assertSame($expected, Positions::move(['a', 'b', 'c', 'd'], $uid, $neighbour, $after));
	}

	public function testNeighbourMustShareTheGroup(): void
	{
		$this->throws(RuntimeException::class, 'same group');

		Positions::move(['a', 'b'], 'a', 'z', true);
	}

	public function testNodeCannotNeighbourItself(): void
	{
		$this->throws(RuntimeException::class, 'next to itself');

		Positions::move(['a', 'b'], 'a', 'a', true);
	}
}
