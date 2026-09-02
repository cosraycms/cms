<?php

declare(strict_types=1);

namespace Cosray\Field\Capability;

use Cosray\Schema\Tool;

interface ToolsAware
{
	public function tools(Tool ...$tools): void;

	/** @return list<string> */
	public function getTools(): array;
}
