<?php

declare(strict_types=1);

namespace Cosray\Panel;

use Cosray\Contract\DashboardCard;
use Cosray\Exception\RuntimeException;
use Cosray\Panel\Dashboard\Drafts;
use Cosray\Panel\Dashboard\Entries;
use Cosray\Panel\Dashboard\Media;
use ReflectionClass;

final class Dashboard
{
	/** @var list<class-string<DashboardCard>|DashboardCard> */
	private array $cards;

	/** @param list<class-string<DashboardCard>|DashboardCard> $cards */
	public function __construct(array $cards = [])
	{
		$this->cards = $this->validated($cards);
	}

	public static function withDefaults(): self
	{
		return new self([
			Entries::class,
			Drafts::class,
			Media::class,
		]);
	}

	/** @param class-string<DashboardCard>|DashboardCard $card */
	public function add(string|DashboardCard $card): self
	{
		$this->cards[] = $this->validate($card);

		return $this;
	}

	/** @param class-string<DashboardCard>|DashboardCard ...$cards */
	public function replace(string|DashboardCard ...$cards): self
	{
		$this->cards = $this->validated($cards);

		return $this;
	}

	/** @return list<class-string<DashboardCard>|DashboardCard> */
	public function cards(): array
	{
		return $this->cards;
	}

	/**
	 * @param list<class-string<DashboardCard>|DashboardCard> $cards
	 * @return list<class-string<DashboardCard>|DashboardCard>
	 */
	private function validated(array $cards): array
	{
		return array_values(array_map($this->validate(...), $cards));
	}

	/**
	 * @param class-string<DashboardCard>|DashboardCard $card
	 * @return class-string<DashboardCard>|DashboardCard
	 */
	private function validate(string|DashboardCard $card): string|DashboardCard
	{
		if ($card instanceof DashboardCard) {
			return $card;
		}

		if (!class_exists($card) || !is_a($card, DashboardCard::class, true)) {
			throw new RuntimeException('Dashboard cards must implement ' . DashboardCard::class . ": {$card}");
		}

		if (!new ReflectionClass($card)->isInstantiable()) {
			throw new RuntimeException("Dashboard card {$card} must be instantiable");
		}

		return $card;
	}
}
