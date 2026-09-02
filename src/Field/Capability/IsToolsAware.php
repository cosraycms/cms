<?php

declare(strict_types=1);

namespace Cosray\Field\Capability;

use Cosray\Schema\Tool;

/** Requires the using field's `$owner` for the project-level fallback. */
trait IsToolsAware
{
	/** @var list<Tool> */
	protected array $tools = [];

	public function tools(Tool ...$tools): void
	{
		$values = [];

		foreach ($tools as $tool) {
			if (!in_array($tool, $values, true)) {
				$values[] = $tool;
			}
		}

		$this->tools = $values;
	}

	/**
	 * Resolved for the panel: the field's own `#[Tools]` list, else the
	 * project's `richtext.tools`, else the built-in default.
	 *
	 * @return list<string>
	 */
	public function getTools(): array
	{
		$tools = $this->tools ?: $this->owner->config()->richtext->tools ?: Tool::defaults();

		return array_map(static fn(Tool $tool): string => $tool->value, $tools);
	}
}
