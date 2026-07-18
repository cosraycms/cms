<?php

declare(strict_types=1);

namespace Cosray;

use Celema\Container\Container;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface as Logger;
use Psr\Log\NullLogger;

/** @internal */
final class ContainerLogger extends AbstractLogger
{
	private NullLogger $fallback;

	public function __construct(
		private Container $container,
	) {
		$this->fallback = new NullLogger();
	}

	/** @param array<string, mixed> $context */
	public function log(mixed $level, string|\Stringable $message, array $context = []): void
	{
		if ($this->container->has(Logger::class)) {
			$logger = $this->container->get(Logger::class);

			if ($logger instanceof Logger && $logger !== $this) {
				$logger->log($level, $message, $context);

				return;
			}
		}

		$this->fallback->log($level, $message, $context);
	}
}
