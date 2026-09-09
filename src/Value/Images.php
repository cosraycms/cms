<?php

declare(strict_types=1);

namespace Cosray\Value;

class Images extends Files
{
	public function __toString(): string
	{
		$out = '';

		for ($i = 0; $i < count($this->files()); $i++) {
			$out .= (string) $this->get($i);
		}

		return $out;
	}

	public function current(): Image
	{
		return $this->get($this->pointer);
	}

	public function get(int $index): Image
	{
		return new Image($this->owner, $this->field, $this->context, $index);
	}

	public function first(): Image
	{
		return new Image($this->owner, $this->field, $this->context, 0);
	}

	/** The gallery tiles' aspect ratio, such as `4/3`; `null` keeps each image's own. */
	public function ratio(): ?string
	{
		$ratio = $this->meta('ratio');

		return is_string($ratio) && $ratio !== '' && $ratio !== 'auto' ? $ratio : null;
	}

	/** Whether the gallery tiles crop to their ratio instead of fitting into it. */
	public function crop(): bool
	{
		return (bool) $this->meta('crop', false);
	}
}
