<?php

declare(strict_types=1);

namespace Cosray;

/**
 * A listing column of a node's stages, made by Column::status(). It shows
 * every stage as an icon unless told otherwise; a collection without one
 * gets it after its first column.
 */
final class StatusColumn
{
	/** @var non-empty-list<Stage> */
	public readonly array $stages;
	public private(set) string $look = 'icons';
	public private(set) bool $present = false;
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

	/**
	 * The main version's icon, with a small dot for each further stage that
	 * holds something.
	 */
	public function compact(): self
	{
		$this->look = 'compact';

		return $this;
	}

	/** Only the stages that hold something. */
	public function present(): self
	{
		$this->present = true;

		return $this;
	}
}
