<?php

declare(strict_types=1);

namespace Cosray\Field\Capability;

trait IsSelectable
{
	/** @var array<string|array<string, mixed>> */
	protected array $options = [];

	/** @param string|array<string, mixed> $option */
	public function add(string|array $option): void
	{
		$this->options[] = $option;
	}

	/** @param array<string|array<string, mixed>> $options */
	public function options(array $options): void
	{
		if (is_array($options[0])) {
			$this->hasLabel = true;
		}

		$this->options = $options;
	}

	/** @return array<string|array<string, mixed>> */
	public function getOptions(): array
	{
		return $this->options;
	}
}
