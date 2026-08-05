<?php

declare(strict_types=1);

namespace Cosray\Console;

use Celema\Container\Container;
use Celema\Core\Factory\Factory;
use Celema\Quma\Database;
use Celema\Verba\Translator;
use Celema\Verba\Verba;
use Cosray\App;
use Cosray\Cms;
use Cosray\Config;
use Cosray\Context;
use Cosray\Locales;
use Cosray\Node\Writer;
use UnexpectedValueException;

/**
 * Lazily resolved services for one console invocation.
 *
 * @internal
 */
final class Runtime
{
	private readonly Container $container;
	private readonly ?Translator $previousTranslator;

	public function __construct(App $app)
	{
		$app->boot();
		$this->container = $app->container()->scope();
		$this->container->add(Context::class, self::context(...))->scoped();
		$this->container->add(Cms::class)->scoped();
		$this->container->add(Writer::class)->scoped();
		$context = $this->container->get(Context::class);
		assert($context instanceof Context, 'The console context must be available');
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
		$command = $this->container->get($class);

		if (!is_object($command)) {
			throw new UnexpectedValueException("Console runtime must resolve {$class} to an object");
		}

		return $command;
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
