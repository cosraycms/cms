<?php

declare(strict_types=1);

namespace Celemas\Cms\Field\Capability\File;

interface Translatable
{
	public function translateFile(bool $translate = true): static;

	public function isFileTranslatable(): bool;
}
