<?php

declare(strict_types=1);

namespace Cosray\Fulltext;

use Celema\Quma\Database;
use Cosray\Exception\RuntimeException;
use Cosray\Locale;
use Cosray\Locales;

final class Configurations
{
	private array $names = [];

	public function __construct(
		private readonly Database $db,
	) {}

	public function load(Locales $locales): void
	{
		// Locales is an Iterator; a search may run inside a caller's locale loop.
		foreach (clone $locales as $locale) {
			$this->for($locale);
		}
	}

	public function for(Locale $locale): string
	{
		$name = $locale->pgDict ?? 'simple';
		if (isset($this->names[$name])) {
			return $this->names[$name];
		}

		$row = $this->db->fulltext->config(['name' => $name])->first();
		if (!$row) {
			throw new RuntimeException(
				"Fulltext configuration '{$name}' for locale '{$locale->id}' is unavailable. Check pgDict and apply the fulltext migration.",
			);
		}

		return $this->names[$name] = $row['config'];
	}
}
