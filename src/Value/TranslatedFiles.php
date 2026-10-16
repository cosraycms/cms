<?php

declare(strict_types=1);

namespace Cosray\Value;

use Override;

class TranslatedFiles extends Files
{
	#[Override]
	public function current(): TranslatedFile
	{
		return $this->get($this->pointer);
	}

	#[Override]
	public function get(int $index): TranslatedFile
	{
		return new TranslatedFile($this->owner, $this->field, $this->context, $index);
	}
}
