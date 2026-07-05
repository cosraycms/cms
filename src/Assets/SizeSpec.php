<?php

declare(strict_types=1);

namespace Cosray\Assets;

/** A validated named entry from the `media.sizes` config. */
final class SizeSpec
{
	public function __construct(
		public readonly string $name,
		public readonly ResizeMode $mode,
		public readonly int $first,
		public readonly ?int $second = null,
		public readonly ?int $pos = null,
		public readonly ?int $quality = null,
		public readonly bool $enlarge = false,
	) {}

	public function size(): Size
	{
		return new Size($this->first, $this->second, $this->pos);
	}
}
