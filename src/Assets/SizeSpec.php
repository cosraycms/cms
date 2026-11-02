<?php

declare(strict_types=1);

namespace Cosray\Assets;

/** A validated named entry from the `media.sizes` config. */
final class SizeSpec
{
	/** Raise it to regenerate every rendition after changing how they are made. */
	private const int PIPELINE = 2;

	public function __construct(
		public readonly string $name,
		public readonly ResizeMode $mode,
		public readonly int $first,
		public readonly ?int $second = null,
		public readonly ?float $pos = null,
		public readonly ?int $quality = null,
		public readonly bool $enlarge = false,
		public readonly ?Format $format = null,
	) {}

	/**
	 * Part of a rendition's name, so a changed definition gets a new file
	 * instead of the one the web server has been serving.
	 */
	public function fingerprint(): string
	{
		return substr(
			hash('xxh3', implode('|', [
				self::PIPELINE,
				$this->mode->value,
				$this->first,
				$this->second,
				$this->pos,
				$this->quality,
				(int) $this->enlarge,
				$this->format?->value,
			])),
			0,
			8,
		);
	}
}
