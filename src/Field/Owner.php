<?php

declare(strict_types=1);

namespace Cosray\Field;

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

	public function origin(): string;

	public function config(): Config;

	public function assets(): Repository;

	public function paths(): UrlPaths;

	/**
	 * Builds a new object for the request this owner is rendered in, the
	 * way Context::create() does, for example a block type.
	 *
	 * @template T of object
	 * @param class-string<T> $class
	 * @param array<string, object> $types
	 * @return T
	 */
	public function create(string $class, array $types = []): object;
}
