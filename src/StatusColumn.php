<?php

declare(strict_types=1);

namespace Cosray;

/**
 * A listing column of a node's stages, made by Column::status(). It shows
 * them as icons up to the last that holds something, the empty ones before
 * it in grey; a collection without one gets it after its first column.
 */
final class StatusColumn
{
	/** @var non-empty-list<Stage> */
	public readonly array $stages;
	public private(set) string $look = 'icons';
	public private(set) bool $fixed = false;
	private ?string $title = null;

	public function __construct(Stage ...$stages)
	{
		$this->stages = array_values(array_filter(
			Stage::cases(),
			static fn(Stage $stage): bool => $stages === [] || in_array($stage, $stages, true),
		)) ?: Stage::cases();
	}

	public function title(string $title): self
	{
		$this->title = $title;

		return $this;
	}

	public function label(): string
	{
		return $this->title ?? __('collection:status');
	}

	/** Dots instead of icons. */
	public function dots(): self
	{
		$this->look = 'dots';

		return $this;
	}

	/** Every stage, also after the last that holds something. */
	public function fixed(): self
	{
		$this->fixed = true;

		return $this;
	}
}
