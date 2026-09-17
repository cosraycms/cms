<?php

declare(strict_types=1);

namespace Cosray;

use Celema\Container\Container;
use Celema\Core\Factory\Factory;
use Celema\Core\Request;
use Celema\Quma\Database;
use Celema\Verba\Translator;
use Celema\Verba\Verba;
use Closure;

final class Context
{
	private ?Assets\Repository $assets = null;
	private ?Node\UrlPaths $paths = null;
	private ?Locales $runtimeLocales = null;
	private ?Locale $runtimeLocale = null;

	/** @var array<string, Translator> */
	private array $translators = [];

	public function __construct(
		public readonly Database $db,
		public readonly ?Request $request,
		public readonly Config $config,
		public readonly Container $container,
		public readonly Factory $factory,
	) {}

	public static function console(
		Database $db,
		Config $config,
		Container $container,
		Factory $factory,
		Locales $locales,
	): self {
		$context = new self($db, null, $config, $container, $factory);
		$context->runtimeLocales = $locales;
		$context->runtimeLocale = $locales->getDefault();

		return $context;
	}

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
		if ($this->runtimeLocales !== null) {
			return $this->runtimeLocales;
		}

		$locales = $this->request?->get('locales', null);

		if (!$locales instanceof Locales) {
			throw new Exception\RuntimeException('Locales are not available in this CMS context');
		}

		return $locales;
	}

	public function locale(): Locale
	{
		if ($this->runtimeLocale !== null) {
			return $this->runtimeLocale;
		}

		$locale = $this->request?->get('locale', null);

		if (!$locale instanceof Locale) {
			throw new Exception\RuntimeException('The current locale is not available in this CMS context');
		}

		return $locale;
	}

	public function defaultLocale(): Locale
	{
		return $this->locales()->getDefault();
	}

	public function localeId(): string
	{
		return $this->locale()->id;
	}

	public function translator(): Translator
	{
		$locale = $this->locale();

		return $this->translators[$locale->id] ??= new Translator(
			$locale->id,
			$this->locales()->catalogs(),
			$locale->fallbacks(),
		);
	}

	public function httpRequest(): Request
	{
		return (
			$this->request ?? throw new Exception\RuntimeException(
				'An HTTP request is not available in this CMS context',
			)
		);
	}

	public function origin(): string
	{
		return $this->request?->origin() ?? '';
	}

	public function withLocale(Locale $locale, Closure $callback): mixed
	{
		$runtimeLocale = $this->runtimeLocale;
		$requestLocale = $this->request?->get('locale', null);
		$translator = Verba::translator();
		$this->runtimeLocale = $locale;
		$this->request?->set('locale', $locale);
		Verba::activate($this->translator());

		try {
			return $callback();
		} finally {
			$this->runtimeLocale = $runtimeLocale;
			$this->request?->set('locale', $requestLocale);

			if ($translator !== null) {
				Verba::activate($translator);
			} else {
				Verba::deactivate();
			}
		}
	}
}
