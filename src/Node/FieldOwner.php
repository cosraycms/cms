<?php

declare(strict_types=1);

namespace Cosray\Node;

use Celema\Core\Request;
use Cosray\Assets\Repository;
use Cosray\Config;
use Cosray\Context;
use Cosray\Field\Owner;
use Cosray\Locale;
use Cosray\Locales;

class FieldOwner implements Owner
{
	public function __construct(
		private readonly Context $context,
		private readonly string $nodeUid,
	) {}

	public function uid(): string
	{
		return $this->nodeUid;
	}

	public function locale(): Locale
	{
		return $this->context->locale();
	}

	public function defaultLocale(): Locale
	{
		return $this->context->request->get('defaultLocale');
	}

	public function locales(): Locales
	{
		return $this->context->locales();
	}

	public function request(): Request
	{
		return $this->context->request;
	}

	public function config(): Config
	{
		return $this->context->config;
	}

	public function assets(): Repository
	{
		return $this->context->assets();
	}

	public function paths(): UrlPaths
	{
		return $this->context->paths();
	}
}
