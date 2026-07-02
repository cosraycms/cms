<?php

declare(strict_types=1);

namespace Cosray\Panel;

/**
 * Additional stylesheets and scripts plugins inject into the panel
 * chrome. Field controls load lazily via element descriptors instead;
 * this is for app-wide assets.
 */
final class Extras
{
	/** @var list<string> */
	private array $css = [];

	/** @var list<array{url: string, module: bool}> */
	private array $js = [];

	public function addCss(string $url): void
	{
		$this->css[] = $url;
	}

	public function addJs(string $url, bool $module = true): void
	{
		$this->js[] = [
			'url' => $url,
			'module' => $module,
		];
	}

	/** @return list<string> */
	public function css(): array
	{
		return $this->css;
	}

	/** @return list<string> */
	public function scripts(): array
	{
		return $this->urls(false);
	}

	/** @return list<string> */
	public function moduleScripts(): array
	{
		return $this->urls(true);
	}

	/** @return list<string> */
	private function urls(bool $module): array
	{
		return array_values(array_map(
			static fn(array $script): string => $script['url'],
			array_filter($this->js, static fn(array $script): bool => $script['module'] === $module),
		));
	}
}
