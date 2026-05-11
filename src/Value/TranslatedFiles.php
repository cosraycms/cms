<?php

declare(strict_types=1);

namespace Celemas\Cms\Value;

class TranslatedFiles extends Files
{
	public function current(): TranslatedFile
	{
		return $this->get($this->pointer);
	}

	public function get(int $index): TranslatedFile
	{
		return new TranslatedFile($this->owner, $this->field, $this->context, $index);
	}
}
