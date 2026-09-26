<?php

declare(strict_types=1);

namespace Cosray\Controller\Panel;

use Celema\Container\Container;
use Celema\Core\Exception\HttpNotFound;
use Celema\Core\Request;
use Celema\Verba\Verba;
use Celema\Wire\Creator;
use Cosray\Cms;
use Cosray\Collection\Listing;
use Cosray\Collection\Ref;
use Cosray\Collection\Schemas;
use Cosray\Config;
use Cosray\Contract\Columns;
use Cosray\Contract\Entries;
use Cosray\Exception\RuntimeException;
use Cosray\Icons\Provider as IconProvider;
use Cosray\Locale;
use Cosray\Navigation;
use Cosray\NavigationItem;
use Cosray\NavLink;
use Cosray\Node\Types;
use Cosray\Panel\Client;
use Cosray\Panel\Extras;
use Cosray\Security\Policy;
use Cosray\User;
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
	private ?Client $client = null;

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
		$layer = $this->layer();
		$dev = $this->devServer();

		return array_merge(
			[
				'debug' => $this->config->debug(),
				'env' => $this->config->env(),
				'layer' => $layer,
				'panelPath' => $panelPath,
				'panelBase' => $panelPath === '/' ? '/' : rtrim($panelPath, '/') . '/',
				'currentPath' => $this->request->uri()->getPath(),
				'area' => static::AREA,
				'dashboard' => $this->config->panel->dashboard,
				'homeUrl' => $this->homeUrl(),
				'contentUrl' => $this->firstUrl($collections),
				'menusUrl' => $this->menusUrl($panelPath),
				'systemUrl' => $this->permits('edit-users') ? $panelPath . '/users' : null,
				'logo' => $this->logo(),
				'localeId' => $localeId,
				'panelLocales' => $this->panelLocales(),
				'account' => $this->account(),
				'config' => $this->config,
				'renderIcon' => $this->renderIcon(...),
				'assetsBase' => $this->client()->url(),
				'importMap' => $this->client()->importMap(),
				'stylesheets' => $this->stylesheets($dev),
				'scripts' => $this->scripts(),
				'moduleScripts' => $this->moduleScripts($dev),
				// Only a full document loads the entry; a swap reuses the modules.
				'modulePreloads' => $layer === 'document' ? $this->client()->preloads() : [],
				'collections' => $collections,
				'rail' => static::AREA === 'content' && $collections !== [],
				'messages' => $this->messages(),
			],
			$data,
		);
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

		return $this->target() === 'frame' ? 'frame' : 'main';
	}

	/** The id of the element an htmx request swaps, if any. */
	protected function target(): string
	{
		// The header reads `<tag>#<id>`; only the id names a panel region.
		$target = $this->request->header('HX-Target');
		$hash = strrpos($target, '#');

		return $hash === false ? '' : substr($target, $hash + 1);
	}

	/**
	 * The `panel` catalog for the active locale as the payload the panel's
	 * verba runtime boots from. This domain holds exactly the strings the
	 * panel scripts use (extracted by the JavascriptScanner), so the browser
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
	 * Where the logo and the panel root lead. Without the dashboard that is the
	 * first remaining masthead area: content, or media for a project without
	 * collections.
	 */
	protected function homeUrl(): string
	{
		$panelPath = $this->panelPath();

		if ($this->config->panel->dashboard) {
			return $panelPath;
		}

		return $this->firstUrl($this->collections()) ?? $panelPath . '/media';
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

	/**
	 * With the dev server, its client injects the panel stylesheet instead.
	 *
	 * @return list<string>
	 */
	private function stylesheets(?string $dev): array
	{
		$panel = $dev === null ? [$this->client()->url('styles/panel.css')] : [];

		return [...$this->config->panel->theme, ...$panel, ...$this->extras()->css()];
	}

	/**
	 * Classic scripts, htmx first: plugins rely on its global.
	 *
	 * @return list<string>
	 */
	private function scripts(): array
	{
		return [...$this->client()->scripts(), ...$this->extras()->scripts()];
	}

	/** @return list<string> */
	private function moduleScripts(?string $dev): array
	{
		$vite = $dev === null ? [] : ["{$dev}/@vite/client", "{$dev}/dev.js"];

		return [...$vite, $this->client()->url('src/panel.js'), ...$this->extras()->moduleScripts()];
	}

	/**
	 * The origin of the optional Vite dev server (`COSRAY_PANEL_DEV`), or null.
	 * It serves the panel stylesheet with hot updates and reloads the page when
	 * a script or view changes; everything else loads as in production.
	 */
	private function devServer(): ?string
	{
		if (!filter_var(env('COSRAY_PANEL_DEV', false), FILTER_VALIDATE_BOOL)) {
			return null;
		}

		$origin = env('COSRAY_PANEL_DEV_ORIGIN', null);

		if (is_string($origin) && trim($origin) !== '') {
			return rtrim(trim($origin), '/');
		}

		$scheme = env('COSRAY_PANEL_DEV_SCHEME', 'http');
		$scheme = is_string($scheme) && in_array($scheme, ['http', 'https'], true) ? $scheme : 'http';
		$port = env('COSRAY_PANEL_DEV_PORT', '2001');
		$port = is_scalar($port) && preg_match('/^[0-9]+$/', (string) $port) ? (string) $port : '2001';
		$host = $this->request->uri()->getHost() ?: $this->request->header('Host');
		$host = trim(explode(':', $host)[0] ?? '');
		$host = preg_match('/^[A-Za-z0-9.-]+$/', $host) === 1 ? $host : 'localhost';

		return "{$scheme}://{$host}:{$port}";
	}

	protected function client(): Client
	{
		return $this->client ??= new Client($this->config);
	}

	private function extras(): Extras
	{
		$extras = $this->container->get(Extras::class);
		assert($extras instanceof Extras, 'The panel extras service must be available');

		return $extras;
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
		return $this->navigation()->items();
	}

	protected function navigation(): Navigation
	{
		$navigation = $this->container->get(Navigation::class);
		assert($navigation instanceof Navigation, 'The navigation service must be available');

		return $navigation;
	}

	/** The registered collection a panel URL names; unknown handles are not found. */
	protected function ref(string $handle): Ref
	{
		try {
			return $this->navigation()->ref($handle);
		} catch (RuntimeException $e) {
			throw new HttpNotFound($this->request, previous: $e);
		}
	}

	/**
	 * The panel listing of a registered collection. A collection class is
	 * only instantiated when it implements Entries or Columns; it is then
	 * autowired per request and shares the listing's CMS instance.
	 */
	protected function listing(Ref $ref): Listing
	{
		$creator = new Creator($this->container);
		$predefined = [Request::class => $this->request];
		$cms = $creator->create(Cms::class, predefinedTypes: $predefined);
		assert($cms instanceof Cms, 'The CMS must be available');
		$collection = is_a($ref->class, Entries::class, true) || is_a($ref->class, Columns::class, true)
			? $creator->create($ref->class, predefinedTypes: $predefined + [Cms::class => $cms])
			: null;
		$schemas = $this->container->get(Schemas::class);
		assert($schemas instanceof Schemas, 'The collection schemas must be available');
		$types = $this->container->get(Types::class);
		assert($types instanceof Types, 'The node type service must be available');

		return new Listing($schemas->of($ref->class), $cms, $types, $collection);
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

	/**
	 * The masthead's menus entry, null for users without the permission.
	 * Cosmetic gating only — the menu routes enforce `edit-menus`
	 * themselves through the permission middleware.
	 */
	private function menusUrl(string $panelPath): ?string
	{
		return $this->permits('edit-menus') ? $panelPath . '/menus' : null;
	}

	protected function permits(string $permission): bool
	{
		$user = $this->request->get('user', null);

		return $this->container
			->get(Policy::class)
			->permits($user instanceof User ? $user : null, $permission);
	}

	/** @return array{name: ?string, initials: string, title: string, detail: string}|null */
	private function account(): ?array
	{
		$user = $this->request->get('user', null);

		if (!$user instanceof User) {
			return null;
		}

		$title = $user->name ?? ($user->username !== '' ? $user->username : $user->email);

		return [
			'name' => $user->name,
			'initials' => $user->initials(),
			'title' => $title,
			'detail' => $user->email !== $title ? $user->email : '',
		];
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
