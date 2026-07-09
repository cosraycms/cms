<?php

declare(strict_types=1);

namespace Cosray;

use Celemas\Core\App;
use Celemas\Core\Plugin as CorePlugin;
use Closure;
use Cosray\Exception\RuntimeException;
use Cosray\Middleware\AddLocale;
use Iterator;
use Psr\Http\Message\ServerRequestInterface as Request;

class Locales implements Iterator, CorePlugin
{
	/** @var array<string, Locale> */
	protected array $locales = [];

	/** @var array<string, string> App translation domains, mapped to their catalog dir. */
	protected array $catalogs = [];

	protected ?string $default = null;
	protected ?Closure $negotiator = null;

	public function load(App $app): void
	{
		$app->middleware(new AddLocale($this));
		$app->register(self::class, $this);
	}

	/**
	 * Register a translation domain and the directory holding its
	 * `<domain>.<locale>.php` catalog files. Domains registered here are
	 * searched before Cosray's own catalogs, so an app can shadow any id.
	 */
	public function catalog(string $domain, string $dir): void
	{
		$this->catalogs = [$domain => $dir] + $this->catalogs;
	}

	/**
	 * The domain cascade for the runtime translator: registered app domains
	 * first, Cosray's bundled catalogs last.
	 *
	 * @return array<string, string>
	 */
	public function catalogs(): array
	{
		$dir = dirname(__DIR__) . '/lang';

		return $this->catalogs + ['cosray' => $dir, 'panel' => $dir];
	}

	public function add(
		string $id,
		string $title,
		?string $fallback = null,
		?string $pgDict = null,
		?array $domains = null,
		?string $urlPrefix = null,
	) {
		// The first locale is always the default one
		if ($this->default === null) {
			$this->default = $id;
		}

		$this->locales[$id] = new Locale($this, $id, $title, $fallback, $pgDict, $domains, $urlPrefix);
	}

	public function get(string $id): Locale
	{
		return $this->locales[$id];
	}

	public function rewind(): void
	{
		reset($this->locales);
	}

	public function current(): Locale
	{
		return current($this->locales);
	}

	public function key(): string
	{
		return key($this->locales);
	}

	public function next(): void
	{
		next($this->locales);
	}

	public function valid(): bool
	{
		return key($this->locales) !== null;
	}

	public function getDefault(): Locale
	{
		// default locale from config file
		if ($this->default === null) {
			throw new RuntimeException('Default locale not available');
		}

		return $this->locales[$this->default];
	}

	public function negotiate(Request $request): Locale
	{
		if ($this->negotiator) {
			return ($this->negotiator)($request, $this->locales, $this->getDefault());
		}

		return $this->fromRequest($request, $this->locales, $this->getDefault());
	}

	public function setNegotiator(Closure $func): void
	{
		$this->negotiator = $func;
	}

	protected function fromRequest(Request $request, array $locales, Locale $default): Locale
	{
		$uri = $request->getUri();

		// From query paramter
		$locale = $request->getQueryParams()['locale'] ?? null;

		if ($locale && $this->exists($locale)) {
			return $locales[$locale];
		}

		// By domain
		$host = strtolower(explode(':', $uri->getHost())[0]);

		foreach ($locales as $locale) {
			foreach ($locale->domains as $domain) {
				if ($host === $domain) {
					return $locale;
				}
			}
		}

		// From URL path prefix. e. g. http://example.com/en_EN/path/to/page
		$prefix = explode('/', trim($uri->getPath(), '/'))[0];

		foreach ($locales as $locale) {
			if ($prefix === $locale->urlPrefix) {
				return $locale;
			}
		}

		// From session
		$session = $request->getAttribute('session', null);

		if ($session) {
			$locale = $session->get('locale', false);

			if ($locale && $this->exists($locale)) {
				return $locales[$locale];
			}
		}

		// From the locales the browser says the user accepts
		// $locale = $this->fromBrowser();
		// if ($locale && $this->exists($locale)) {
		//    return $locales[$locale];
		// }

		return $default;
	}

	protected function exists(string $id): bool
	{
		return array_key_exists($id, $this->locales);
	}

	protected function fromBrowser(): string|false
	{
		if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
			preg_match_all(
				'/([a-z]{1,8}(-[a-z]{1,8})?)\s*(;\s*q\s*=\s*(1|0\.[0-9]+))?/i',
				$_SERVER['HTTP_ACCEPT_LANGUAGE'],
				$matches,
			);

			if (count($matches[1])) {
				$langs = array_combine($matches[1], $matches[4]);

				foreach ($langs as $lang => $val) {
					if ($val !== '') {
						continue;
					}

					$langs[$lang] = 1;
				}

				arsort($langs, SORT_NUMERIC);

				foreach ($langs as $lang => $val) {
					if ($this->exists($lang)) {
						return $lang;
					}

					$lang = str_replace('-', '_', $lang);

					if ($this->exists($lang)) {
						return $lang;
					}

					$lang = strtok($lang, '_');

					if ($this->exists($lang)) {
						return $lang;
					}
				}
			}
		}

		return false;
	}
}
