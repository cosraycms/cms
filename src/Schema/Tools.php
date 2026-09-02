<?php

declare(strict_types=1);

namespace Cosray\Schema;

use Attribute;
use Cosray\Exception\RuntimeException;

/**
 * Arguments may mix single cases with lists, because attribute argument
 * lists forbid unpacking a preset: `#[Tools(Tool::DEFAULT, Tool::Align)]`.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class Tools
{
	/** @var list<Tool> */
	public array $tools;

	public function __construct(Tool|array ...$tools)
	{
		$flat = [];

		foreach ($tools as $entry) {
			foreach (is_array($entry) ? $entry : [$entry] as $tool) {
				if (!$tool instanceof Tool) {
					throw new RuntimeException('#[Tools] lists may only contain Tool cases');
				}

				$flat[] = $tool;
			}
		}

		$this->tools = $flat;
	}
}
