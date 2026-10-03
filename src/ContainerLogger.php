<?php

declare(strict_types=1);

namespace Cosray;

use Celema\Container\Container;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface as Logger;
use Psr\Log\LogLevel;
use Stringable;
use Throwable;

/**
 * Hands records to the logger the application registers, which usually
 * happens after the error handler received this proxy.
 *
 * Without a registered logger, records go to PHP's error log. The error
 * handler marks the diagnostics it logs as handled and renders exceptions
 * itself, so PHP would never report them otherwise. Debug and info records
 * are left out: the fallback stands in for PHP's error reporting, not for
 * application chatter.
 *
 * @internal
 */
final class ContainerLogger extends AbstractLogger
{
	public function __construct(
		private Container $container,
	) {}

	/** @param array<string, mixed> $context */
	public function log(mixed $level, string|Stringable $message, array $context = []): void
	{
		if ($this->container->has(Logger::class)) {
			$logger = $this->container->get(Logger::class);

			if ($logger instanceof Logger && $logger !== $this) {
				$logger->log($level, $message, $context);

				return;
			}
		}

		$this->fallback($level, (string) $message, $context);
	}

	/** @param array<string, mixed> $context */
	private function fallback(mixed $level, string $message, array $context): void
	{
		if ($level === LogLevel::DEBUG || $level === LogLevel::INFO) {
			return;
		}

		$label = is_string($level) || is_int($level) ? strtoupper((string) $level) : 'LOG';
		$replacements = [];

		foreach ($context as $key => $value) {
			if (is_scalar($value) || $value instanceof Stringable) {
				$replacements['{' . $key . '}'] = (string) $value;
			}
		}

		$line = $label . ': ' . strtr($message, $replacements);
		$exception = $context['exception'] ?? null;

		if ($exception instanceof Throwable) {
			// Includes the trace and the chain of previous exceptions.
			$line .= ': ' . (string) $exception;
		}

		error_log($line);
	}
}
