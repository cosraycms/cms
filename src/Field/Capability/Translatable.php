<?php

declare(strict_types=1);

namespace Celemas\Cms\Field\Capability;

interface Translatable
{
	public function translate(bool $translate = true): static;

	public function isTranslatable(): bool;
}
