<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Field;

use Cosray\Field\Text;
use Override;

/** A plugin-style field that stores a node uid as text and declares it. */
class TestPlace extends Text
{
	#[Override]
	public static function references(array $field): array
	{
		$uid = $field['value']['zxx'] ?? null;

		return ['assets' => [], 'nodes' => is_string($uid) && $uid !== '' ? [$uid] : []];
	}
}
