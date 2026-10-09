<?php

declare(strict_types=1);

namespace Cosray\I18n;

use Celema\Verba\Catalog;
use Celema\Verba\Tool\Message;
use Celema\Verba\Tool\Scanner;
use Override;

/**
 * The messages an app's translation domain maintains: what its scanners find,
 * less the bare messages Cosray translates itself.
 *
 * A bare translation call cascades from the app's catalogs into Cosray's
 * `cosray` and `panel` catalogs (see Locales::catalogs()). Such a message,
 * like the label of a built-in collection in the app's navigation, needs no
 * entry in the app catalog, where it would only show up as a gap. It stays
 * when the app translates it in its own catalog, which shadows Cosray's text,
 * so that `i18n:sync --prune` keeps the override. A message with an explicit
 * domain does not cascade, so it always stays.
 *
 * @internal
 */
final class AppMessages implements Scanner
{
	/**
	 * @param list<Scanner> $scanners
	 * @param array<string, string> $catalogs the app domain's catalog files, keyed by locale
	 */
	public function __construct(
		private readonly array $scanners,
		private readonly array $catalogs,
	) {}

	/**
	 * Loads the catalogs only now, so registering the command costs nothing
	 * for the other console commands.
	 *
	 * @return list<Message>
	 */
	#[Override]
	public function scan(): array
	{
		$cosray = self::cosrayCatalogs();
		$app = [];

		foreach ($this->catalogs as $locale => $file) {
			$app[] = Catalog::load($file, $locale);
		}

		$messages = [];

		foreach ($this->scanners as $scanner) {
			foreach ($scanner->scan() as $message) {
				$cascades = $message->domain === null;

				if ($cascades && self::translates($cosray, $message) && !self::translates($app, $message)) {
					continue;
				}

				$messages[] = $message;
			}
		}

		return $messages;
	}

	/** @return list<string> */
	#[Override]
	public function warnings(): array
	{
		$warnings = [];

		foreach ($this->scanners as $scanner) {
			$warnings = [...$warnings, ...$scanner->warnings()];
		}

		return $warnings;
	}

	/**
	 * The catalogs that end the runtime cascade, in every locale Cosray ships.
	 *
	 * @return list<Catalog>
	 */
	private static function cosrayCatalogs(): array
	{
		$dir = dirname(__DIR__, 2) . '/lang';
		$catalogs = [];

		foreach (['cosray', 'panel'] as $domain) {
			foreach (glob("{$dir}/{$domain}.*.php") ?: [] as $file) {
				$locale = substr(basename($file, '.php'), strlen($domain) + 1);
				$catalogs[] = Catalog::load($file, $locale);
			}
		}

		return $catalogs;
	}

	/**
	 * Whether a catalog holds a translation, as the runtime counts it: a
	 * string or a non-empty list of plural forms.
	 *
	 * @param list<Catalog> $catalogs
	 */
	private static function translates(array $catalogs, Message $message): bool
	{
		foreach ($catalogs as $catalog) {
			$entry = $catalog->get($message->id, $message->context);

			if ($entry !== null && $entry !== []) {
				return true;
			}
		}

		return false;
	}
}
