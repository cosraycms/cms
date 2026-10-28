<?php

declare(strict_types=1);

namespace Cosray;

use Celema\Container\Container;
use Celema\Core\Factory\Factory;
use Celema\Core\Request;
use Celema\Quma\Database;
use Celema\Verba\Translator;
use Celema\Verba\Verba;
use Celema\Wire\Creator;
use Closure;
use ReflectionClass;

final class Context
{
	private ?Assets\Repository $assets = null;
	private ?Node\UrlPaths $paths = null;
	private ?Title\Sort $titleSort = null;
	private ?Locales $runtimeLocales = null;
	private ?Locale $runtimeLocale = null;

	/** The request carrying the locale withLocale() switched to, while its callback runs. */
	private ?Request $localeRequest = null;

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

	/**
	 * Builds a new object for this request. Its constructor can ask for
	 * this Context, the Request, Config, Database, the core Factory and the
	 * request's container, plus the given types; other arguments are
	 * autowired through the request's container. Nodes, embedded objects,
	 * collections, block types and dashboard cards are built this way.
	 * Without a request, as in commands, jobs and the node writer, the
	 * Request is a stand-in that throws once it is used.
	 *
	 * @template T of object
	 * @param class-string<T> $class
	 * @param array<string, object> $types
	 * @return T
	 */
	public function create(string $class, array $types = []): object
	{
		$base = [
			self::class => $this,
			Config::class => $this->config,
			Database::class => $this->db,
			Factory::class => $this->factory,
			Container::class => $this->container,
		];

		$base[Request::class] = $this->currentRequest() ?? self::absentRequest();
		$instance = new Creator($this->container)->create($class, predefinedTypes: $types + $base);
		assert($instance instanceof $class, 'The creator returns the requested class');

		return $instance;
	}

	public function assets(): Assets\Repository
	{
		return $this->assets ??= new Assets\Repository($this->db, $this->config);
	}

	public function paths(): Node\UrlPaths
	{
		return $this->paths ??= new Node\UrlPaths($this->db);
	}

	public function titleSort(): Title\Sort
	{
		return $this->titleSort ??= new Title\Sort($this->db);
	}

	public function locales(): Locales
	{
		if ($this->runtimeLocales !== null) {
			return $this->runtimeLocales;
		}

		$locales = $this->currentRequest()?->get('locales', null);

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

		$locale = $this->currentRequest()?->get('locale', null);

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
		return $this->currentRequest() ?? throw new Exception\RuntimeException(
			'An HTTP request is not available in this CMS context',
		);
	}

	/**
	 * The scheme and host absolute URLs start with: the request's, or else
	 * those of `app.url`. A locale with domains of its own gets its first
	 * one in place of a host that is not among them, as only they answer its
	 * paths, unless the request on that host was answered in the locale.
	 */
	public function origin(): string
	{
		$origin = $this->request?->origin() ?: self::originOf($this->config->app->url);
		$requested = $this->request?->get('locale', null);
		$locale = $this->runtimeLocale ?? $requested;

		if ($origin === '' || !$locale instanceof Locale || $locale->domains === []) {
			return $origin;
		}

		$host = strtolower((string) parse_url($origin, PHP_URL_HOST));
		$answered = $requested instanceof Locale && $requested->id === $locale->id;

		if ($answered || in_array($host, $locale->domains, true)) {
			return $origin;
		}

		return self::originOf($origin, $locale->domain());
	}

	/**
	 * Runs the callback in another locale. Code that reads the locale off
	 * the request, such as templates, gets a request carrying it.
	 */
	public function withLocale(Locale $locale, Closure $callback): mixed
	{
		$runtimeLocale = $this->runtimeLocale;
		$localeRequest = $this->localeRequest;
		$translator = Verba::translator();
		$this->runtimeLocale = $locale;
		$this->localeRequest = $this->request?->with('locale', $locale);
		Verba::activate($this->translator());

		try {
			return $callback();
		} finally {
			$this->runtimeLocale = $runtimeLocale;
			$this->localeRequest = $localeRequest;

			if ($translator !== null) {
				Verba::activate($translator);
			} else {
				Verba::deactivate();
			}
		}
	}

	private function currentRequest(): ?Request
	{
		return $this->localeRequest ?? $this->request;
	}

	/** Lets a node whose form handler reads the request still be written and indexed. */
	private static function absentRequest(): Request
	{
		return new ReflectionClass(Request::class)->newLazyProxy(static function (): never {
			throw new Exception\RuntimeException(
				'There is no HTTP request here: commands, jobs and the node writer run without one.',
			);
		});
	}

	/** The scheme, host and port of an absolute URL, with another host if one is given. */
	private static function originOf(?string $url, ?string $host = null): string
	{
		if ($url === null) {
			return '';
		}

		$port = parse_url($url, PHP_URL_PORT);

		return (
			(string) parse_url($url, PHP_URL_SCHEME)
				. '://'
				. ($host ?? (string) parse_url($url, PHP_URL_HOST))
				. (is_int($port) ? ":{$port}" : '')
		);
	}
}
