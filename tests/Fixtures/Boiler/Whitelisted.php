<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Boiler;

use Cosray\Markup;

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

	public function markup(string $content): Markup
	{
		return new Markup("<p>{$content}</p>");
	}
}
