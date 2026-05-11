<?php

declare(strict_types=1);

namespace Celemas\Cms\Tests\Fixtures\Boiler;

class Whitelisted extends WhitelistBase
{
	public function __toString(): string
	{
		return '<h1>headline</h1>';
	}

	public function paragraph(string $content): string
	{
		return "<p>{$content}</p>";
	}
}
