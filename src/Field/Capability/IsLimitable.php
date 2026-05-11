<?php

declare(strict_types=1);

namespace Celemas\Cms\Field\Capability;

use Celemas\Cms\Exception\RuntimeException;

trait IsLimitable
{
	protected int $limitMin = 0;
	protected int $limitMax = -1;

	public function limit(int $max, int $min = 0): static
	{
		if ($max < 1) {
			throw new RuntimeException('Limit max must be >= 1');
		}

		if ($min < 0) {
			throw new RuntimeException('Limit min must be >= 0');
		}

		if ($min > $max) {
			throw new RuntimeException('Limit min must be <= max');
		}

		$this->limitMax = $max;
		$this->limitMin = $min;

		return $this;
	}

	public function getLimitMin(): int
	{
		return $this->limitMin;
	}

	public function getLimitMax(): int
	{
		return $this->limitMax;
	}

	protected function allowsMultipleItems(): bool
	{
		return $this->getLimitMax() !== 1;
	}

	/** @return string[] */
	private function limitValidators(): array
	{
		$validators = [];

		if ($this->limitMax >= 1) {
			$validators[] = 'maxitems:' . $this->getLimitMax();
		}

		if ($this->getLimitMin() > 0) {
			$validators[] = 'minitems:' . $this->getLimitMin();
		}

		return $validators;
	}
}
