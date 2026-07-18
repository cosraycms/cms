<?php

declare(strict_types=1);

namespace Cosray\Field;

use Celema\Core\Request;
use Cosray\Assets\Repository;
use Cosray\Config;
use Cosray\Locale;
use Cosray\Locales;
use Cosray\Node\UrlPaths;

interface Owner
{
	public function uid(): string;

	public function locale(): Locale;

	public function defaultLocale(): Locale;

	public function locales(): Locales;

	public function request(): Request;

	public function config(): Config;

	public function assets(): Repository;

	public function paths(): UrlPaths;
}
