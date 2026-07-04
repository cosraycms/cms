<?php

declare(strict_types=1);

namespace Cosray\Panel;

use Cosray\Config;
use Cosray\Locales;

/**
 * System payload embedded into panel editor pages. It feeds the
 * standalone window.Cosray bridge (and through it every element
 * control).
 */
final class System
{
	public function __construct(
		private readonly Config $config,
		private readonly Locales $locales,
	) {}

	public function payload(): array
	{
		$config = $this->config;
		$locales = [];

		foreach ($this->locales as $locale) {
			$locales[] = [
				'id' => $locale->id,
				'title' => $locale->title,
				'fallback' => $locale->fallback,
			];
		}

		return [
			'debug' => $config->debug(),
			'env' => $config->env(),
			'csrfToken' => '',
			'locale' => $this->locales->getDefault()->id,
			'defaultLocale' => $this->locales->getDefault()->id,
			'locales' => $locales,
			'customLocales' => [],
			'prefix' => $config->path->prefix,
			'assets' => $config->path->assets,
			'allowedFiles' => [
				'file' => array_merge(...array_values($config->upload->file)),
				'image' => array_merge(...array_values($config->upload->image)),
				'video' => array_merge(...array_values($config->upload->video)),
			],
		];
	}
}
