<?php

declare(strict_types=1);

namespace Cosray\Field\Control;

/**
 * Maps named rich controls to the custom element that renders them.
 *
 * Registered names serialize as element descriptors, so the editor
 * island only ever interprets primitives, group/repeater and element.
 * Later registrations win — a plugin may replace a built-in editor.
 */
final class Registry
{
	/** @var array<string, array{tag: string, module: string}> */
	private array $entries = [];

	public function register(string $name, string $tag, string $module): void
	{
		$this->entries[$name] = [
			'tag' => $tag,
			'module' => $module,
		];
	}

	public function has(string $name): bool
	{
		return isset($this->entries[$name]);
	}

	/** @return array{tag: string, module: string} */
	public function get(string $name): array
	{
		return $this->entries[$name];
	}

	public static function withDefaults(): self
	{
		// Built-in rich controls move here stage by stage as they are
		// converted to cosray-shipped custom elements.
		return new self();
	}
}
