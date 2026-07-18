<?php

declare(strict_types=1);

namespace Cosray\Controller\Panel;

use Celema\Container\Container;
use Celema\Core\Factory\Factory;
use Celema\Core\Request;
use Celema\Core\Response;
use Cosray\Auth as CmsAuth;
use Cosray\Config;
use Cosray\Locales;
use Cosray\Users;

final class Preferences extends Panel
{
	public function __construct(
		Config $config,
		Container $container,
		Request $request,
		private readonly CmsAuth $auth,
		private readonly Users $users,
	) {
		parent::__construct($config, $container, $request);
	}

	/**
	 * Persists the user's panel UI language. A value outside the selectable
	 * set resets the preference to NULL (inherit the negotiated default).
	 */
	public function locale(Factory $factory): Response
	{
		$user = $this->auth->user();

		if ($user !== null) {
			$locale = $this->formData()['locale'] ?? null;

			$this->users->savePanelLocale(
				$user->id,
				is_string($locale) && in_array($locale, $this->available(), true) ? $locale : null,
			);
		}

		return $this->redirect($factory, $this->refererPath());
	}

	/** @return list<string> */
	private function available(): array
	{
		$locales = $this->request->get('locales', null);

		return $locales instanceof Locales ? $locales->panelLocales() : [];
	}

	/**
	 * The panel page the switcher was used on, so the reload lands where
	 * the user was. Anything outside the panel falls back to the index.
	 */
	private function refererPath(): string
	{
		$referer = $this->request->header('Referer');
		$path = parse_url($referer, PHP_URL_PATH);
		$query = parse_url($referer, PHP_URL_QUERY);

		if (is_string($path) && str_starts_with($path, $this->panelPath())) {
			return is_string($query) && $query !== '' ? $path . '?' . $query : $path;
		}

		return $this->panelPath();
	}

	private function redirect(Factory $factory, string $target): Response
	{
		$response = Response::create($factory);

		if ($this->request->hasHeader('HX-Request')) {
			return $response
				->status(200)
				->header('HX-Redirect', $target);
		}

		return $response
			->status(303)
			->header('Location', $target);
	}
}
