<?php

declare(strict_types=1);

namespace Cosray\Config;

final class Richtext
{
	public function __construct(
		private readonly \Cosray\Config $config,
	) {}

	/**
	 * Declared paragraph classes: `'classname' => 'Readable label'`.
	 * `default` is implicit and always available.
	 *
	 * @var array<string, string>
	 */
	public array $classes {
		get => (array) $this->config->get('richtext.classes');
	}

	/**
	 * Declared text styles for the `style` mark, same shape. Empty by
	 * default — the mark is unusable until a project declares styles.
	 *
	 * @var array<string, string>
	 */
	public array $styles {
		get => (array) $this->config->get('richtext.styles');
	}
}
