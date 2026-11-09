<?php

declare(strict_types=1);

namespace Cosray\Console;

use Celema\Container\Container;
use Celema\Core\Factory\Factory;
use Celema\Quma\Database;
use Celema\Verba\Translator;
use Celema\Verba\Verba;
use Closure;
use Cosray\Cms;
use Cosray\Config;
use Cosray\Context;
use Cosray\Locales;
use Cosray\Node\Writer;

/**
 * Services resolved lazily in a scope without a request: for one console
 * invocation, or for a batch of queued jobs. Making one changes no global
 * state; its translator is active while run() runs a callback, or where
 * the owner activates translator() for longer.
 *
 * @internal
 */
final class Runtime
{
	private readonly Container $container;

	/** @param Container $container the booted app's container */
	public function __construct(Container $container)
	{
		$this->container = $container->scope();
		$this->container->add(Context::class, self::context(...))->scoped();
		$this->container->add(Cms::class)->scoped();
		$this->container->add(Writer::class)->scoped();
	}

	/** The translator of the scope's locale, the default content locale. */
	public function translator(): Translator
	{
		return $this->container->instance(Context::class)->translator();
	}

	/**
	 * Runs the callback with the scope's translator active, and activates
	 * the one active before again, also when the callback throws.
	 *
	 * @template T
	 *
	 * @param Closure(): T $callback
	 * @return T
	 */
	public function run(Closure $callback): mixed
	{
		$previous = Verba::translator();
		Verba::activate($this->translator());

		try {
			return $callback();
		} finally {
			if ($previous !== null) {
				Verba::activate($previous);
			} else {
				Verba::deactivate();
			}
		}
	}

	/** @param class-string $class */
	public function get(string $class): object
	{
		return $this->container->instance($class);
	}

	private static function context(
		Database $db,
		Config $config,
		Container $container,
		Factory $factory,
		Locales $locales,
	): Context {
		return Context::console($db, $config, $container, $factory, $locales);
	}
}
