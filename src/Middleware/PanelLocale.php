<?php

declare(strict_types=1);

namespace Cosray\Middleware;

use Celemas\Verba\Translator;
use Celemas\Verba\Verba;
use Cosray\Config;
use Cosray\Locales;
use Cosray\User;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as Handler;

/**
 * Negotiates the panel UI language independently of the content locale:
 * the user's stored preference, then config `panel.locale`, then the
 * browser's Accept-Language, then English. Activates a translator for the
 * chosen locale with the remaining panel locales as fallback chain and
 * restores the content translator afterwards. The content locale
 * attributes stay untouched, so editing and serialization are unaffected.
 */
class PanelLocale implements Middleware
{
	public function __construct(
		protected Config $config,
	) {}

	public function process(Request $request, Handler $handler): Response
	{
		$locales = $request->getAttribute('locales', null);

		if (!$locales instanceof Locales) {
			return $handler->handle($request);
		}

		$available = $locales->panelLocales();

		if ($available === []) {
			return $handler->handle($request);
		}

		$id = $this->negotiate($request, $available);
		$translator = new Translator($id, $locales->catalogs(), $this->fallback($id, $available));
		$previous = Verba::translator();
		Verba::activate($translator);

		try {
			return $handler->handle(
				$request
					->withAttribute('panelLocale', $id)
					->withAttribute('panelLocales', $available)
					->withAttribute('translator', $translator),
			);
		} finally {
			if ($previous !== null) {
				Verba::activate($previous);
			} else {
				Verba::deactivate();
			}
		}
	}

	/** @param non-empty-list<string> $available */
	protected function negotiate(Request $request, array $available): string
	{
		$user = $request->getAttribute('user', null);

		if (
			$user instanceof User
			&& $user->panelLocale !== null
			&& in_array($user->panelLocale, $available, true)
		) {
			return $user->panelLocale;
		}

		$configured = $this->config->panel->locale;

		if ($configured !== null && in_array($configured, $available, true)) {
			return $configured;
		}

		return (
			$this->fromBrowser($request, $available)
			?? (in_array('en', $available, true) ? 'en' : $available[0])
		);
	}

	/**
	 * The locales tried per string when the negotiated one lacks a
	 * translation: config default first, then English, then the rest.
	 *
	 * @param non-empty-list<string> $available
	 * @return list<string>
	 */
	protected function fallback(string $id, array $available): array
	{
		$chain = array_unique([$this->config->panel->locale ?? 'en', 'en', ...$available]);

		return array_values(array_filter(
			$chain,
			static fn(string $locale) => $locale !== $id && in_array($locale, $available, true),
		));
	}

	/** @param non-empty-list<string> $available */
	protected function fromBrowser(Request $request, array $available): ?string
	{
		$lookup = [];

		foreach ($available as $id) {
			$lookup[strtolower(str_replace('_', '-', $id))] = $id;
		}

		foreach ($this->acceptedLanguages($request) as $tag) {
			$tag = strtolower($tag);
			$primary = explode('-', $tag)[0];

			if (isset($lookup[$tag])) {
				return $lookup[$tag];
			}

			if (isset($lookup[$primary])) {
				return $lookup[$primary];
			}
		}

		return null;
	}

	/** @return list<string> Language tags in descending quality order. */
	protected function acceptedLanguages(Request $request): array
	{
		$accepted = [];
		$position = 0;

		foreach (explode(',', $request->getHeaderLine('Accept-Language')) as $part) {
			$params = explode(';', trim($part));
			$tag = trim($params[0]);

			if (preg_match('/^[A-Za-z]{1,8}(?:-[A-Za-z0-9]{1,8})*$/', $tag) !== 1) {
				continue;
			}

			$quality = 1.0;

			foreach (array_slice($params, 1) as $param) {
				if (preg_match('/^\s*q\s*=\s*(0(?:\.\d{0,3})?|1(?:\.0{0,3})?)\s*$/', $param, $m) !== 1) {
					continue;
				}

				$quality = (float) $m[1];
			}

			if ($quality > 0) {
				$accepted[] = ['tag' => $tag, 'quality' => $quality, 'position' => $position];
			}

			$position++;
		}

		usort(
			$accepted,
			static fn(array $a, array $b) => (
				$b['quality'] <=> $a['quality'] ?: $a['position'] <=> $b['position']
			),
		);

		return array_column($accepted, 'tag');
	}
}
