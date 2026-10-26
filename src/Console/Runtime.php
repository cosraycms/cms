<?php

declare(strict_types=1);

namespace Cosray\Console;

use Celema\Container\Container;
use Celema\Core\Factory\Factory;
use Celema\Quma\Database;
use Celema\Verba\Translator;
use Celema\Verba\Verba;
use Cosray\Cms;
use Cosray\Config;
use Cosray\Context;
use Cosray\Locales;
use Cosray\Node\Writer;

/**
 * Services resolved lazily in a scope without a request: for one console
 * invocation, or for a batch of queued jobs.
 *
 * @internal
 */
final class Runtime
{
	private readonly Container $container;
	private readonly ?Translator $previousTranslator;

	/** @param Container $container the booted app's container */
	public function __construct(Container $container)
	{
		$this->container = $container->scope();
		$this->container->add(Context::class, self::context(...))->scoped();
		$this->container->add(Cms::class)->scoped();
		$this->container->add(Writer::class)->scoped();
		$context = $this->container->instance(Context::class);
		$this->previousTranslator = Verba::translator();
		Verba::activate($context->translator());
	}

	public function __destruct()
	{
		if ($this->previousTranslator !== null) {
			Verba::activate($this->previousTranslator);
		} else {
			Verba::deactivate();
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
