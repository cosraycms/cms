<?php

declare(strict_types=1);

namespace Cosray\Field\Capability;

use Cosray\Exception\RuntimeException;

trait IsSelectable
{
	/** @var array<string|array<string, mixed>> */
	protected array $options = [];

	/** @param string|array<string, mixed> $option */
	public function add(string|array $option): void
	{
		$this->options[] = $option;
	}

	/**
	 * A list of values or `{value, label}` arrays, or labels keyed by value.
	 *
	 * @param array<string|array<string, mixed>> $options
	 */
	public function options(array $options): void
	{
		if (array_is_list($options)) {
			$this->options = $options;

			return;
		}

		$this->options = [];

		foreach ($options as $value => $label) {
			if (!is_string($label)) {
				throw new RuntimeException("Option '{$value}' needs a string label");
			}

			$this->options[] = ['value' => (string) $value, 'label' => $label];
		}
	}

	/** @return array<string|array<string, mixed>> */
	public function getOptions(): array
	{
		return $this->options;
	}
}
