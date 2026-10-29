<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Celema\Verba\Plurals;
use Celema\Verba\Tool\JavascriptScanner;
use Celema\Verba\Tool\Message;
use Celema\Verba\Tool\PhpScanner;
use Cosray\Block\Registry;
use Cosray\I18n\SchemaScanner;
use Cosray\Tests\TestCase;

final class TranslationCatalogTest extends TestCase
{
	public function testCosrayCatalogsCoverPhpMessages(): void
	{
		$root = dirname(__DIR__, 2);
		$messages = new PhpScanner([$root . '/src', $root . '/panel/views'])->scan();

		$this->assertCatalogsCover($messages, 'cosray');
	}

	public function testCosrayCatalogsCoverBuiltinBlockSchemas(): void
	{
		$messages = new SchemaScanner(Registry::withDefaults()->all())->scan();

		$this->assertCatalogsCover($messages, 'cosray');
	}

	public function testPanelCatalogsCoverBrowserMessages(): void
	{
		$root = dirname(__DIR__, 2);
		$messages = new JavascriptScanner([$root . '/panel/src'])->scan();

		$this->assertCatalogsCover($messages, 'panel');
	}

	public function testEveryPanelLanguageShipsBothCatalogs(): void
	{
		$this->assertContains('en', self::locales('cosray'));
		$this->assertSame(self::locales('cosray'), self::locales('panel'));
	}

	public function testCatalogsMatchEnglishIdsAndPluralForms(): void
	{
		foreach (['cosray', 'panel'] as $domain) {
			$reference = self::catalog($domain, 'en')['messages'];

			foreach (self::locales($domain) as $locale) {
				$catalog = self::catalog($domain, $locale);
				$entries = $catalog['messages'];
				$forms = Plurals::forms($catalog['plural'] ?? $locale);
				$mismatched = array_values(array_filter(
					array_keys(array_intersect_key($reference, $entries)),
					static fn(string $id): bool => is_array($reference[$id])
						? !is_array($entries[$id]) || count($entries[$id]) !== $forms
						: !is_string($entries[$id]),
				));

				$this->assertSame(
					[],
					array_values(array_diff(array_keys($reference), array_keys($entries))),
					"Missing {$domain}.{$locale} ids",
				);
				$this->assertSame(
					[],
					array_values(array_diff(array_keys($entries), array_keys($reference))),
					"{$domain}.{$locale} ids missing from {$domain}.en",
				);
				$this->assertSame(
					[],
					$mismatched,
					"{$domain}.{$locale} entries need a string, or a list of {$forms} plural forms, like {$domain}.en",
				);
			}
		}
	}

	/** @param list<Message> $messages */
	private function assertCatalogsCover(array $messages, string $domain): void
	{
		$ids = array_values(array_unique(array_map(
			static fn(Message $message): string => $message->id,
			$messages,
		)));
		sort($ids);
		$plurals = array_values(array_unique(array_map(
			static fn(Message $message): string => $message->id,
			array_filter($messages, static fn(Message $message): bool => $message->plural !== null),
		)));

		foreach (self::locales($domain) as $locale) {
			$entries = self::catalog($domain, $locale)['messages'];
			$missing = array_values(array_diff($ids, array_keys($entries)));
			$untranslated = array_values(array_filter(
				$ids,
				static fn(string $id): bool => !self::translated($entries[$id] ?? null),
			));
			$singular = array_values(array_filter(
				$plurals,
				static fn(string $id): bool => !is_array($entries[$id] ?? []),
			));

			$this->assertSame([], $missing, "Missing {$domain}.{$locale} translations");
			$this->assertSame([], $untranslated, "Untranslated {$domain}.{$locale} messages");
			$this->assertSame([], $singular, "{$domain}.{$locale} needs plural forms for ids used with __n()");
		}
	}

	/** @return list<string> */
	private static function locales(string $domain): array
	{
		$files = glob(dirname(__DIR__, 2) . "/lang/{$domain}.*.php") ?: [];

		return array_map(
			static fn(string $file): string => substr(basename($file, '.php'), strlen($domain) + 1),
			$files,
		);
	}

	/** @return array{messages: array<string, mixed>, plural?: string} */
	private static function catalog(string $domain, string $locale): array
	{
		/** @var array{messages: array<string, mixed>, plural?: string} */
		return require dirname(__DIR__, 2) . "/lang/{$domain}.{$locale}.php";
	}

	private static function translated(mixed $value): bool
	{
		if (is_string($value)) {
			return $value !== '';
		}

		return (
			is_array($value)
				&& $value !== []
				&& array_all($value, static fn(mixed $form): bool => is_string($form) && $form !== '')
		);
	}
}
