<?php

declare(strict_types=1);

namespace Cosray;

function escape(string $string): string
{
	return htmlspecialchars($string, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

function env(string $key, mixed $default = null): mixed
{
	if (array_key_exists($key, $_SERVER)) {
		return $_SERVER[$key];
	}

	if (array_key_exists($key, $_ENV)) {
		return $_ENV[$key];
	}

	return $default;
}
