<?php

declare(strict_types=1);

namespace Celemas\Cms\Config;

final class Panel
{
	/** @var list<non-empty-string>|null */
	private ?array $themeCache = null;

	public function __construct(
		private readonly \Celemas\Cms\Config $config,
	) {}

	/** @var non-empty-string */
	public string $path {
		get => $this->config->get('path.panel');
	}

	/** @var list<non-empty-string> */
	public array $theme {
		get => $this->themeCache ??= self::strings($this->config->get('panel.theme'));
	}

	/** @var ?non-empty-string */
	public ?string $logo {
		get => $this->config->get('panel.logo');
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
