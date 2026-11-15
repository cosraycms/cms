<?php

declare(strict_types=1);

namespace Cosray\Config;

use Cosray\Exception\RuntimeException;

final class Panel
{
	/** @var list<non-empty-string>|null */
	private ?array $themeCache = null;

	public function __construct(
		private readonly \Cosray\Config $config,
	) {}

	public string $path {
		get => $this->config->get('panel.path');
	}

	/** @var list<non-empty-string> */
	public array $theme {
		get => $this->themeCache ??= self::strings($this->config->get('panel.theme'));
	}

	public ?string $logo {
		get => $this->config->get('panel.logo');
	}

	public bool $dashboard {
		get => $this->config->get('panel.dashboard');
	}

	/**
	 * Origins that may frame the panel and its embedded editor, like
	 * `https://admin.example.com`.
	 *
	 * @var list<string>
	 */
	public array $embed {
		get => array_values(array_filter(
			(array) $this->config->get('panel.embed'),
			static fn(mixed $origin): bool => is_string($origin) && preg_match('#^https?://[^/\s]+$#i', $origin) === 1,
		));
	}

	/** Default panel UI language; NULL negotiates from the browser. */
	public ?string $locale {
		get => $this->config->get('panel.locale');
	}

	/**
	 * Region of the panel's date and number formats, like `GB`, when the
	 * browser names none for the panel language.
	 */
	public ?string $region {
		get => self::region($this->config->get('panel.region'));
	}

	private static function region(mixed $region): ?string
	{
		if ($region === null || $region === '') {
			return null;
		}

		if (!is_string($region) || preg_match('/^(?:[A-Za-z]{2}|\d{3})$/D', $region) !== 1) {
			throw new RuntimeException('`panel.region` takes a region code, like GB or AT.');
		}

		return strtoupper($region);
	}

	/** @return list<non-empty-string> */
	private static function strings(mixed $value): array
	{
		if ($value === null) {
			return [];
		}

		if (is_string($value)) {
			$value = trim($value);

			return $value === '' ? [] : [$value];
		}

		return array_values($value);
	}
}
