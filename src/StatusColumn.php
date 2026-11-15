<?php

declare(strict_types=1);

namespace Cosray;

use Cosray\Node\Wrapper;
use Override;

/**
 * A listing column of a node's stages, made by Column::status(). It shows
 * the ones that hold something as icons, each in its place, an empty one
 * between them as a stretch of the line. A collection without one gets it
 * after its first column.
 */
final class StatusColumn extends Column
{
	/** @var non-empty-list<Stage> */
	public readonly array $stages;
	public private(set) string $look = 'icons';
	public private(set) bool $fixed = false;

	public function __construct(Stage ...$stages)
	{
		$this->stages = array_values(array_filter(
			Stage::cases(),
			static fn(Stage $stage): bool => $stages === [] || in_array($stage, $stages, true),
		)) ?: Stage::cases();
		$this->title = __('collection:status');
	}

	public function title(string $title): self
	{
		$this->title = $title;

		return $this;
	}

	#[Override]
	public function kind(): string
	{
		return 'status';
	}

	/** @return array{} the stages come from the listing's status facts */
	#[Override]
	public function get(Wrapper $node): array
	{
		return [];
	}

	/** Dots instead of icons. */
	public function dots(): self
	{
		$this->look = 'dots';

		return $this;
	}

	/** Every stage, the empty ones as grey marks. */
	public function fixed(): self
	{
		$this->fixed = true;

		return $this;
	}
}
