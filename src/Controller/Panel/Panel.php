<?php

declare(strict_types=1);

namespace Cosray\Controller\Panel;

use Celemas\Container\Container;
use Celemas\Core\Request;
use Celemas\Verba\Verba;
use Cosray\Config;
use Cosray\Contract\Icons;
use Cosray\Locale;
use Cosray\Navigation;
use Cosray\Panel\Extras;

use function Cosray\env;

abstract class Panel
{
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

		return array_merge([
			'debug' => $this->config->debug(),
			'env' => $this->config->env(),
			'boosted' => $this->request->hasHeader('HX-Boosted'),
			'htmx' => $this->request->hasHeader('HX-Request'),
			'panelPath' => $panelPath,
			'currentPath' => $this->request->uri()->getPath(),
			'logo' => $this->logo(),
			'localeId' => $localeId,
			'config' => $this->config,
			'renderIcon' => $this->renderIcon(...),
			'stylesheets' => $this->stylesheets($panelPath),
			'scripts' => $this->scripts($panelPath),
			'moduleScripts' => $this->moduleScripts($panelPath),
			'collections' => $this->collections(),
			'messages' => $this->messages(),
		], $data);
	}

	/**
	 * The `panel` catalog for the active locale as the canonical JSON payload
	 * the panel reads through its `__` lookup. This domain holds exactly the
	 * strings the Svelte panel uses (extracted by the JavascriptScanner), so the
	 * browser never receives backend-only messages. Empty when no translator is
	 * active (e.g. outside the request pipeline).
	 *
	 * @return array{plural: string, messages: array<string, string|list<string>>}
	 */
	protected function messages(): array
	{
		return Verba::translator()?->export('panel') ?? ['plural' => $this->localeId(), 'messages' => []];
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
		$data = $this->request->form() ?? [];
		$contentType = strtolower(trim(explode(';', $this->request->header('Content-Type'))[0]));

		if ($data === [] && $contentType === 'application/json') {
			$decoded = $this->request->json();

			if (is_array($decoded)) {
				$data = $decoded;
			}
		}

		if ($data === [] && $contentType === 'application/x-www-form-urlencoded') {
			parse_str((string) $this->request->body(), $parsed);
			$data = $parsed;
		}

		return $data;
	}

	protected function localeId(): string
	{
		$locale = $this->request->get('locale', null);

		return $locale instanceof Locale ? $locale->id : 'en';
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
		return [
			"{$panelPath}/assets/app/vendor/htmx.js",
			...$this->extras()->scripts(),
		];
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
		$static = $this->publicPanelStaticDir();

		return is_file($static . '/panel.js') && is_file($static . '/panel.css');
	}

	protected function publicPanelStaticDir(): string
	{
		return $this->publicPanelDir() . '/static';
	}

	private function publicPanelDir(): string
	{
		$path = trim($this->panelPath(), '/');
		$public = rtrim($this->config->path->public, '/\\');

		return $path === '' ? $public : "{$public}/{$path}";
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

		$service = $this->container->get(Icons::class);

		if (!$service instanceof Icons) {
			return '';
		}

		$args = $icon['args'] ?? [];

		return $service->icon(trim($id), is_array($args) ? $args : []);
	}
}
