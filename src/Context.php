<?php

declare(strict_types=1);

namespace Cosray;

use Celemas\Container\Container;
use Celemas\Core\Factory\Factory;
use Celemas\Core\Request;
use Celemas\Quma\Database;

final class Context
{
	private ?Assets\Repository $assets = null;

	public function __construct(
		public readonly Database $db,
		public readonly Request $request,
		public readonly Config $config,
		public readonly Container $container,
		public readonly Factory $factory,
	) {}

	public function assets(): Assets\Repository
	{
		return $this->assets ??= new Assets\Repository($this->db, $this->config);
	}

	public function locales(): Locales
	{
		return $this->request->get('locales');
	}

	public function locale(): Locale
	{
		return $this->request->get('locale');
	}

	public function localeId(): string
	{
		return $this->request->get('locale')->id;
	}
}
