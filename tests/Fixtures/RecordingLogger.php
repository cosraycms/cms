<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures;

use Override;
use Psr\Log\AbstractLogger;
use Stringable;

/** Keeps each record as its level and interpolated message. */
final class RecordingLogger extends AbstractLogger
{
	/** @var list<array{string, string}> */
	public array $records = [];

	#[Override]
	public function log(mixed $level, string|Stringable $message, array $context = []): void
	{
		$replacements = [];

		foreach ($context as $key => $value) {
			if (is_scalar($value) || $value instanceof Stringable) {
				$replacements['{' . $key . '}'] = (string) $value;
			}
		}

		$this->records[] = [(string) $level, strtr((string) $message, $replacements)];
	}
}
