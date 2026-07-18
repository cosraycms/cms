<?php

declare(strict_types=1);

namespace Cosray;

use Celema\Container\Container;
use Celema\Core\Factory\Factory;
use Celema\Core\Request;
use Celema\Quma\Database;
use Celema\Verba\Translator;

final class Context
{
	private ?Assets\Repository $assets = null;
	private ?Node\UrlPaths $paths = null;

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

	public function paths(): Node\UrlPaths
	{
		return $this->paths ??= new Node\UrlPaths($this->db);
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

	public function translator(): Translator
	{
		return $this->request->get('translator');
	}
}
