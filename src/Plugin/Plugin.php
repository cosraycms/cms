<?php

declare(strict_types=1);

namespace Cosray\Plugin;

/**
 * A runtime plugin installed via Composer and registered in the app
 * bootstrap: `$app->plugin(ShopPlugin::class)` or through the
 * `plugins` config key.
 *
 * Plugins must be constructible without arguments; everything they
 * need arrives through the Registrar. Optional behavior comes from app
 * config: options live at '{id}.{option}' settings keys and are read
 * via Registrar::option() with an inline default, so a plugin works
 * with zero configuration.
 */
interface Plugin
{
	/**
	 * Stable identifier, e.g. 'acme-shop'.
	 *
	 * Lowercase letters, digits and dashes, with at least one dash —
	 * like custom element names. The dash keeps every name the plugin
	 * creates apart from Cosray's own: its config keys '{id}.{option}',
	 * panel routes under '{panel}/{id}', template namespace, translation
	 * domain, migration namespace and asset URLs. Its permissions are
	 * named '{id}.{name}' by convention.
	 */
	public function id(): string;

	public function register(Registrar $cms): void;
}
