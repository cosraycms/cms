<?php

declare(strict_types=1);

namespace Cosray\Plugin;

/**
 * A runtime plugin installed via Composer and registered in the app
 * bootstrap: `$app->plugin(ShopPlugin::class)` or through the
 * `plugins` config key.
 *
 * Plugins must be constructible without arguments; everything they
 * need arrives through the Registrar.
 */
interface Plugin
{
	/**
	 * Stable identifier, e.g. 'acme-shop'.
	 *
	 * Used for asset URLs, template namespaces and error messages.
	 */
	public function id(): string;

	public function register(Registrar $cms): void;
}
