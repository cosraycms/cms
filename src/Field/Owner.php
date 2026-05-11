<?php

declare(strict_types=1);

namespace Celemas\Cms\Field;

use Celemas\Cms\Config;
use Celemas\Cms\Locale;
use Celemas\Cms\Locales;
use Celemas\Core\Request;

interface Owner
{
	public function uid(): string;

	public function locale(): Locale;

	public function defaultLocale(): Locale;

	public function locales(): Locales;

	public function request(): Request;

	public function config(): Config;
}
