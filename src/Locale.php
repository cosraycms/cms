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
	 * The fallback locale applies only to content translations stored in the
	 * database. UI strings translated through verba fall back to the message
	 * id, not through this locale chain.
	 */
	public function fallback(): ?Locale
	{
		return $this->fallback ? $this->locales->get($this->fallback) : null;
	}

	public function domain(int $index = 0): string
	{
		return $this->domains[$index];
	}
}
