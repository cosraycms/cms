<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Api\MergePatch;
use Cosray\Tests\TestCase;

final class MergePatchTest extends TestCase
{
	public function testObjectsMergeMemberByMember(): void
	{
		$this->assertSame(
			['title' => ['value' => ['en' => 'Old', 'de' => 'Neu']], 'intro' => ['value' => ['en' => 'Hi']]],
			MergePatch::apply(
				['title' => ['value' => ['en' => 'Old', 'de' => 'Alt']], 'intro' => ['value' => ['en' => 'Hi']]],
				['title' => ['value' => ['de' => 'Neu']]],
			),
		);
	}

	public function testNullRemovesAndListsReplace(): void
	{
		$this->assertSame(
			['tags' => ['c'], 'title' => ['value' => []]],
			MergePatch::apply(
				['tags' => ['a', 'b'], 'title' => ['value' => ['de' => 'Alt']], 'intro' => ['value' => []]],
				['tags' => ['c'], 'title' => ['value' => ['de' => null]], 'intro' => null],
			),
		);
	}

	public function testANonObjectPatchReplacesTheTarget(): void
	{
		$this->assertSame('text', MergePatch::apply(['a' => 1], 'text'));
		$this->assertSame(['b' => 2], MergePatch::apply('text', ['b' => 2]));
	}
}
