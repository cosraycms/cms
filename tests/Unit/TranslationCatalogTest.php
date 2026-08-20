<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Celema\Verba\Tool\JavascriptScanner;
use Celema\Verba\Tool\Message;
use Celema\Verba\Tool\PhpScanner;
use Cosray\Tests\TestCase;

final class TranslationCatalogTest extends TestCase
{
	public function testCosrayCatalogsCoverPhpMessages(): void
	{
		$root = dirname(__DIR__, 2);
		$messages = new PhpScanner([$root . '/src', $root . '/panel/views'])->scan();

		$this->assertCatalogsCover($messages, 'cosray');
	}

	public function testPanelCatalogsCoverBrowserMessages(): void
	{
		$root = dirname(__DIR__, 2);
		$messages = new JavascriptScanner([$root . '/panel/src'])->scan();

		$this->assertCatalogsCover($messages, 'panel');
	}

	/** @param list<Message> $messages */
	private function assertCatalogsCover(array $messages, string $domain): void
	{
		$ids = array_values(array_unique(array_map(
			static fn(Message $message): string => $message->id,
			$messages,
		)));
		sort($ids);

		foreach (['de', 'en'] as $locale) {
			$catalog = require dirname(__DIR__, 2) . "/lang/{$domain}.{$locale}.php";
			$entries = $catalog['messages'] ?? [];
			$missing = array_values(array_diff($ids, array_keys($entries)));
			$untranslated = array_values(array_filter(
				$ids,
				static fn(string $id): bool => !self::translated($entries[$id] ?? null),
			));

			$this->assertSame([], $missing, "Missing {$domain}.{$locale} translations");
			$this->assertSame([], $untranslated, "Untranslated {$domain}.{$locale} messages");
		}
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
