<?php

declare(strict_types=1);

namespace Cosray\Field\Capability;

use Cosray\Schema\TranslateMode;

interface Translatable
{
	public function translate(TranslateMode $mode = TranslateMode::Symmetric): static;

	public function isTranslatable(): bool;

	public function translateMode(): ?TranslateMode;

	public function supportsTranslateMode(TranslateMode $mode): bool;
}
