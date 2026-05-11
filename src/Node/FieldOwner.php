<?php

declare(strict_types=1);

namespace Celemas\Cms\Node;

use Celemas\Cms\Config;
use Celemas\Cms\Context;
use Celemas\Cms\Field\Owner;
use Celemas\Cms\Locale;
use Celemas\Cms\Locales;
use Celemas\Core\Request;

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
}
