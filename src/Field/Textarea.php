<?php

declare(strict_types=1);

namespace Cosray\Field;

use Override;

class Textarea extends Text implements Capability\Multiline
{
	use Capability\IsMultiline;

	#[Override]
	public function control(): Control
	{
		return Control::textarea();
	}

	#[Override]
	public function structure(mixed $value = null): array
	{
		return $this->getTranslatableStructure('textarea', $value);
	}
}
