<?php

declare(strict_types=1);

namespace Cosray\Controller\Panel;

use Celema\Container\Container;
use Celema\Core\Request;
use Celema\Verba\Verba;
use Cosray\Config;
use Cosray\Icons\Provider as IconProvider;
use Cosray\Locale;
use Cosray\Navigation;
use Cosray\NavigationItem;
use Cosray\NavLink;
use Cosray\Panel\Extras;
use Cosray\Util\Form;

use function Cosray\env;

abstract class Panel
{
	/**
	 * Which masthead area this screen belongs to. The rail renders only for
	 * `content`, which is the default because a project's own panel pages put
	 * their entry in that rail without knowing this constant exists.
	 */
	protected const string AREA = 'content';

	protected string $panelDir;

	public function __construct(
		protected Config $config,
		protected Container $container,
		protected readonly Request $request,
	) {
		$this->panelDir = __DIR__ . '/../../../panel';
	}

	protected function context(array $data = []): array
	{
		$panelPath = $this->panelPath();
		$localeId = $this->localeId();
		$collections = $this->collections();

		return array_merge([
			'debug' => $this->config->debug(),
			'env' => $this->config->env(),
			'layer' => $this->layer(),
			'panelPath' => $panelPath,
			'panelBase' => $panelPath === '/' ? '/' : rtrim($panelPath, '/') . '/',
			'currentPath' => $this->request->uri()->getPath(),
			'area' => static::AREA,
			'contentUrl' => $this->firstUrl($collections),
			'logo' => $this->logo(),
			'localeId' => $localeId,
			'panelLocales' => $this->panelLocales(),
			'config' => $this->config,
			'renderIcon' => $this->renderIcon(...),
			'stylesheets' => $this->stylesheets($panelPath),
			'scripts' => $this->scripts($panelPath),
			'moduleScripts' => $this->moduleScripts($panelPath),
			'collections' => $collections,
			'rail' => static::AREA === 'content' && $collections !== [],
			'messages' => $this->messages(),
		], $data);
	}

	/**
	 * How much of the panel a response renders. htmx names the element it is
	 * about to swap, and that boundary is where the layer templates stop: the
	 * content region for navigation inside an area, the frame for an area
	 * switch, the whole body for a history restore, the document otherwise.
	 */
	protected function layer(): string
	{
		// A restore swaps the body. It has to come first: htmx sends this header
		// alone, without the ones every other request carries.
		if ($this->request->hasHeader('HX-History-Restore-Request')) {
			return 'shell';
		}

		if (!$this->request->hasHeader('HX-Request')) {
			return 'document';
		}

		// Anything else aimed at the body, which htmx flags as a full render.
		if ($this->request->header('HX-Request-Type') === 'full') {
			return 'shell';
		}

		// The target reads `<tag>#<id>`; only the id names a panel region.
		$target = $this->request->header('HX-Target');
		$hash = strrpos($target, '#');

		return $hash !== false && substr($target, $hash + 1) === 'frame' ? 'frame' : 'main';
	}

	/**
	 * The `panel` catalog for the active locale as the payload the panel's
	 * verba runtime boots from. This domain holds exactly the strings the
	 * Svelte panel uses (extracted by the JavascriptScanner), so the browser
	 * never receives backend-only messages. Empty when no translator is
	 * active (e.g. outside the request pipeline).
	 *
	 * @return array{locale: string, domains: list<array{domain: string, plural: string, messages: array<string, string|list<string>>}>}
	 */
	protected function messages(): array
	{
		return Verba::translator()?->exportMany(['panel']) ?? ['locale' => $this->localeId(), 'domains' => []];
	}

	protected function panelPath(): string
	{
		return $this->config->panel->path;
	}

	/**
	 * Submitted form data with fallbacks for request pipelines that do
	 * not populate the parsed body (JSON and urlencoded raw bodies).
	 */
	protected function formData(): array
	{
		return Form::body($this->request);
	}

	protected function localeId(): string
	{
		$panelLocale = $this->request->get('panelLocale', null);

		if (is_string($panelLocale)) {
			return $panelLocale;
		}

		$locale = $this->request->get('locale', null);

		return $locale instanceof Locale ? $locale->id : 'en';
	}

	/**
	 * The selectable panel UI languages, mapped to their native names
	 * (e.g. `['de' => 'Deutsch', 'en' => 'English']`).
	 *
	 * @return array<string, string>
	 */
	protected function panelLocales(): array
	{
		$ids = $this->request->get('panelLocales', []);
		$titles = [];

		/** @var string $id */
		foreach (is_array($ids) ? $ids : [] as $id) {
			$title = \Locale::getDisplayLanguage($id, $id);
			$titles[$id] = $title === $id ? $id : $title;
		}

		return $titles;
	}

	private function stylesheets(string $panelPath): array
	{
		$stylesheets = $this->config->panel->theme;

		if (!$this->panelDev() && $this->hasPanelStatic()) {
			$stylesheets[] = "{$panelPath}/static/panel.css";
		}

		return [...$stylesheets, ...$this->extras()->css()];
	}

	private function scripts(string $panelPath): array
	{
		if ($this->panelDev()) {
			$origin = $this->panelDevOrigin();
			$scripts = ["{$origin}/node_modules/htmx.org/dist/htmx.min.js"];
		} else {
			$scripts = $this->hasPanelStatic() ? ["{$panelPath}/static/htmx.js"] : [];
		}

		return [...$scripts, ...$this->extras()->scripts()];
	}

	private function moduleScripts(string $panelPath): array
	{
		if ($this->panelDev()) {
			$origin = $this->panelDevOrigin();

			return [
				"{$origin}/@vite/client",
				"{$origin}/src/panel.ts",
				...$this->extras()->moduleScripts(),
			];
		}

		$scripts = $this->hasPanelStatic() ? ["{$panelPath}/static/panel.js"] : [];

		return [...$scripts, ...$this->extras()->moduleScripts()];
	}

	private function extras(): Extras
	{
		$extras = $this->container->get(Extras::class);
		assert($extras instanceof Extras, 'The panel extras service must be available');

		return $extras;
	}

	protected function hasPanelStatic(): bool
	{
		$static = $this->panelAssetsDir();

		return (
			is_file($static . '/panel.js')
				&& is_file($static . '/panel.css')
				&& is_file($static . '/htmx.js')
		);
	}

	protected function panelAssetsDir(): string
	{
		return rtrim($this->config->path->panelAssets, '/\\');
	}

	private function panelDev(): bool
	{
		return filter_var(env('COSRAY_PANEL_DEV', false), FILTER_VALIDATE_BOOL);
	}

	private function panelDevOrigin(): string
	{
		$origin = env('COSRAY_PANEL_DEV_ORIGIN', null);

		if (is_string($origin) && trim($origin) !== '') {
			return rtrim(trim($origin), '/');
		}

		$scheme = env('COSRAY_PANEL_DEV_SCHEME', 'http');
		$scheme = is_string($scheme) && in_array($scheme, ['http', 'https'], true) ? $scheme : 'http';
		$port = env('COSRAY_PANEL_DEV_PORT', '2001');
		$port = is_scalar($port) && preg_match('/^[0-9]+$/', (string) $port) ? (string) $port : '2001';

		return "{$scheme}://{$this->panelDevHost()}:{$port}";
	}

	private function panelDevHost(): string
	{
		$host = $this->request->uri()->getHost();

		if ($host === '') {
			$host = $this->request->header('Host');
		}

		$host = trim(explode(':', $host)[0] ?? '');

		return preg_match('/^[A-Za-z0-9.-]+$/', $host) === 1 ? $host : 'localhost';
	}

	private function logo(): ?string
	{
		$logo = $this->config->panel->logo;

		if ($logo === null) {
			return null;
		}

		$logo = trim((string) $logo);

		return $logo === '' ? null : $logo;
	}

	protected function collections(): array
	{
		/** @var Navigation $navigation */
		$navigation = $this->container->get(Navigation::class);

		return $navigation->items();
	}

	/**
	 * Where the masthead's content entry goes: the first entry of the rail, in
	 * the rail's own order. Null when a project defines no collections.
	 *
	 * @param list<NavigationItem> $items
	 */
	private function firstUrl(array $items): ?string
	{
		foreach ($items as $item) {
			if ($item instanceof NavLink) {
				return $item->url;
			}

			$slug = $item->slug();

			if ($slug !== null) {
				return $this->panelPath() . '/collection/' . $slug;
			}

			$url = $this->firstUrl($item->children());

			if ($url !== null) {
				return $url;
			}
		}

		return null;
	}

	/** @param array{id: string, args?: array<array-key, mixed>}|null $icon */
	private function renderIcon(?array $icon): string
	{
		if ($icon === null) {
			return '';
		}

		$id = $icon['id'] ?? null;

		if (!is_string($id) || trim($id) === '') {
			return '';
		}

		$service = $this->container->get(IconProvider::class);

		if (!$service instanceof IconProvider) {
			return '';
		}

		$args = $icon['args'] ?? [];

		return $service->icon(trim($id), is_array($args) ? $args : []);
	}
}
