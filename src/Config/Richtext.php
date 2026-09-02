<?php

declare(strict_types=1);

namespace Cosray\Config;

use Cosray\Schema\Tool;

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

	/**
	 * Project-wide toolbar, as `Tool` cases or their string values.
	 * Empty by default — fields fall back to the built-in set; a field's
	 * own `#[Tools]` overrides this list.
	 *
	 * @var list<Tool>
	 */
	public array $tools {
		get => array_values(array_map(
			static fn(mixed $tool): Tool => $tool instanceof Tool ? $tool : Tool::from((string) $tool),
			(array) $this->config->get('richtext.tools'),
		));
	}
}
