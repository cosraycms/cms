<?php

declare(strict_types=1);

namespace Cosray;

class Locale
{
	public readonly string $urlPrefix;
	public readonly array $domains;

	public function __construct(
		protected readonly Locales $locales,
		public readonly string $id,
		public readonly string $title,
		public readonly ?string $fallback = null,
		public readonly ?string $pgDict = null,
		?array $domains = null,
		?string $urlPrefix = null,
	) {
		if ($domains) {
			$this->domains = array_map(static fn($d) => strtolower($d), $domains);
		} else {
			$this->domains = [];
		}

		$this->urlPrefix = $urlPrefix ?: $id;
	}

	/**
	 * The next locale in the fallback chain, used for content translations
	 * stored in the database and for UI strings translated through verba.
	 */
	public function fallback(): ?Locale
	{
		return $this->fallback ? $this->locales->get($this->fallback) : null;
	}

	/**
	 * The ids of the whole fallback chain in resolution order, stopping
	 * before a locale repeats.
	 *
	 * @return list<string>
	 */
	public function fallbacks(): array
	{
		$chain = [];
		$locale = $this->fallback();

		while ($locale !== null && $locale->id !== $this->id && !in_array($locale->id, $chain, true)) {
			$chain[] = $locale->id;
			$locale = $locale->fallback();
		}

		return $chain;
	}

	public function domain(int $index = 0): string
	{
		return $this->domains[$index];
	}
}
