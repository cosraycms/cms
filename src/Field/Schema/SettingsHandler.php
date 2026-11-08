<?php

declare(strict_types=1);

namespace Cosray\Field\Schema;

use Cosray\Field\Field;
use Override;

class SettingsHandler extends Handler
{
	#[Override]
	public function apply(object $meta, Field $field): void {}

	#[Override]
	public function properties(object $meta, Field $field): array
	{
		return ['settings' => true];
	}
}
