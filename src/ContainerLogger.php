<?php

declare(strict_types=1);

namespace Cosray;

use Celema\Container\Container;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface as Logger;
use Stringable;

/**
 * Hands records to the logger registered in the container when they are
 * written, so the error handler, created before the application registers
 * its logger, still uses it.
 *
 * @internal
 */
final class ContainerLogger extends AbstractLogger
{
	private ?ErrorLog $fallback = null;

	public function __construct(
		private Container $container,
	) {}

	/** @param array<string, mixed> $context */
	public function log(mixed $level, string|Stringable $message, array $context = []): void
	{
		$logger = $this->container->has(Logger::class) ? $this->container->get(Logger::class) : null;

		if (!$logger instanceof Logger || $logger === $this) {
			$this->fallback ??= new ErrorLog();
			$logger = $this->fallback;
		}

		$logger->log($level, $message, $context);
	}
}
