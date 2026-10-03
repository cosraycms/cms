<?php

declare(strict_types=1);

namespace Cosray;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Stringable;
use Throwable;

/**
 * The logger an application uses until it registers its own: records go to
 * PHP's error log. The error handler marks the diagnostics it logs as handled
 * and renders exceptions itself, so PHP would never report them otherwise.
 * Debug and info records are left out: this logger stands in for PHP's error
 * reporting, not for application chatter.
 *
 * @internal
 */
final class ErrorLog extends AbstractLogger
{
	/** @param array<array-key, mixed> $context */
	public function log(mixed $level, string|Stringable $message, array $context = []): void
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

		$line = $label . ': ' . strtr((string) $message, $replacements);
		$exception = $context['exception'] ?? null;

		if ($exception instanceof Throwable) {
			// Includes the trace and the chain of previous exceptions.
			$line .= ': ' . (string) $exception;
		}

		error_log($line);
	}
}
