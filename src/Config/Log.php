<?php

declare(strict_types=1);

namespace Cosray\Config;

final class Log
{
	public function __construct(
		private readonly \Cosray\Config $config,
	) {}

	/** The file records are appended to, or null for PHP's error log. */
	public ?string $file {
		get {
			$file = $this->config->get('log.file');

			return is_string($file) && $file !== '' ? $file : null;
		}
	}

	/** The lowest level written to the file. */
	public string $level {
		get => (string) $this->config->get('log.level');
	}
}
