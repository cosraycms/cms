<?php

declare(strict_types=1);

namespace Cosray\Console;

use Celema\Container\Container;
use Celema\Core\Factory\Factory;
use Celema\Core\Request;
use Cosray\App;
use Cosray\Cms;
use Cosray\Context;
use Cosray\Locales;
use UnexpectedValueException;

/**
 * Lazily resolved services for one console invocation.
 *
 * @internal
 */
final class Runtime
{
	private readonly Container $container;

	public function __construct(App $app)
	{
		$app->boot();
		$this->container = $app->container()->scope();
		$this->container->add(Request::class, self::request(...))->scoped();
		$this->container->add(Context::class)->scoped();
		$this->container->add(Cms::class)->scoped();
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

	private static function request(Factory $factory, Locales $locales): Request
	{
		$request = new Request($factory->serverRequest());
		$request->set('locales', $locales);
		$request->set('defaultLocale', $locales->getDefault());
		$request->set('locale', $locales->getDefault());

		return $request;
	}
}
