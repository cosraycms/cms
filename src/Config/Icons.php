<?php

declare(strict_types=1);

namespace Celemas\Cms\Config;

final class Icons
{
	/** @var list<non-empty-string>|null */
	private ?array $localPathsCache = null;

	private ?Iconify $iconifyCache = null;

	public function __construct(
		private readonly \Celemas\Cms\Config $config,
	) {}

	/** @var list<non-empty-string> */
	public array $localPaths {
		get => $this->localPathsCache ??= self::strings($this->config->get('icons.local.paths'));
	}

	public Iconify $iconify {
		get => $this->iconifyCache ??= new Iconify($this->config);
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
