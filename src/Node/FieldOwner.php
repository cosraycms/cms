<?php

declare(strict_types=1);

namespace Cosray\Node;

use Cosray\Assets\Repository;
use Cosray\Config;
use Cosray\Context;
use Cosray\Field\Owner;
use Cosray\Locale;
use Cosray\Locales;
use Override;

class FieldOwner implements Owner
{
	public function __construct(
		private readonly Context $context,
		private readonly string $nodeUid,
	) {}

	#[Override]
	public function uid(): string
	{
		return $this->nodeUid;
	}

	#[Override]
	public function locale(): Locale
	{
		return $this->context->locale();
	}

	#[Override]
	public function defaultLocale(): Locale
	{
		return $this->context->defaultLocale();
	}

	#[Override]
	public function locales(): Locales
	{
		return $this->context->locales();
	}

	#[Override]
	public function origin(): string
	{
		return $this->context->origin();
	}

	#[Override]
	public function config(): Config
	{
		return $this->context->config;
	}

	#[Override]
	public function assets(): Repository
	{
		return $this->context->assets();
	}

	#[Override]
	public function paths(): UrlPaths
	{
		return $this->context->paths();
	}

	#[Override]
	public function create(string $class, array $types = []): object
	{
		return $this->context->create($class, $types);
	}
}
