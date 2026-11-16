<?php

declare(strict_types=1);

namespace Cosray\Panel;

/**
 * Additional stylesheets and scripts plugins inject into the panel
 * chrome. Field controls load lazily via element descriptors instead;
 * this is for app-wide assets. A file of a plugin's asset directory is
 * named by the plugin and its path, and gets its URL under the panel
 * revision; anything else is a URL.
 */
final class Extras
{
	/** @var list<array{url: string, plugin: ?string}> */
	private array $css = [];

	/** @var list<array{url: string, plugin: ?string, module: bool}> */
	private array $js = [];

	public function addCss(string $url, ?string $plugin = null): void
	{
		$this->css[] = ['url' => $url, 'plugin' => $plugin];
	}

	public function addJs(string $url, bool $module = true, ?string $plugin = null): void
	{
		$this->js[] = [
			'url' => $url,
			'plugin' => $plugin,
			'module' => $module,
		];
	}

	/** @return list<string> */
	public function css(Client $client): array
	{
		return array_map(static fn(array $css): string => self::url($css, $client), $this->css);
	}

	/** @return list<string> */
	public function scripts(Client $client): array
	{
		return $this->urls(false, $client);
	}

	/** @return list<string> */
	public function moduleScripts(Client $client): array
	{
		return $this->urls(true, $client);
	}

	/** Whether a path is a URL rather than a path in a plugin's asset directory. */
	public static function isUrl(string $path): bool
	{
		return str_starts_with($path, '/') || preg_match('~\A[a-z][a-z0-9+.-]*:~i', $path) === 1;
	}

	/** @return list<string> */
	private function urls(bool $module, Client $client): array
	{
		return array_values(array_map(
			static fn(array $script): string => self::url($script, $client),
			array_filter($this->js, static fn(array $script): bool => $script['module'] === $module),
		));
	}

	/** @param array{url: string, plugin: ?string, ...} $entry */
	private static function url(array $entry, Client $client): string
	{
		return $entry['plugin'] === null ? $entry['url'] : $client->pluginUrl($entry['plugin'], $entry['url']);
	}
}
