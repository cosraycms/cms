<?php

declare(strict_types=1);

namespace Cosray\Config;

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
