<?php

declare(strict_types=1);

namespace Cosray;

use Cosray\Exception\RuntimeException;
use Cosray\Maintenance\State;

/**
 * Turns maintenance mode on and off. The mode is a file rather than a
 * database row, so it holds while migrations rebuild the database, and every
 * release that points `maintenance.file` to the same path sees it.
 */
final class Maintenance
{
	public function __construct(
		private readonly string $file,
	) {}

	public static function of(Config $config): self
	{
		return new self($config->maintenance->file);
	}

	public function file(): string
	{
		return $this->file;
	}

	/** The current mode, or null while the site runs normally. */
	public function state(): ?State
	{
		// Long-running workers would otherwise keep a stale answer.
		clearstatcache(true, $this->file);

		if (!is_file($this->file)) {
			return null;
		}

		$contents = is_readable($this->file) ? file_get_contents($this->file) : false;
		$data = is_string($contents) && $contents !== '' ? json_decode($contents, true) : null;

		return State::fromArray(is_array($data) ? $data : []);
	}

	/**
	 * Turns maintenance mode on, replacing an earlier state, and returns the
	 * secret that lets a browser through.
	 */
	public function on(bool $full = false, int $retry = 60, ?string $message = null): string
	{
		$secret = bin2hex(random_bytes(16));
		$state = new State(
			full: $full,
			retry: max(1, $retry),
			message: $message === null || trim($message) === '' ? null : trim($message),
			since: gmdate('Y-m-d\TH:i:s\Z'),
			secretHash: State::hash($secret),
		);
		$directory = dirname($this->file);

		if (!is_dir($directory) || !is_writable($directory)) {
			throw new RuntimeException("Cannot write the maintenance file in {$directory}");
		}

		$temporary = tempnam($directory, '.maintenance-');

		if ($temporary === false) {
			throw new RuntimeException("Could not write the maintenance file in {$directory}");
		}

		file_put_contents($temporary, json_encode($state->toArray(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
		// Web server and console may run as different users of one group.
		chmod($temporary, 0o644);

		if (!rename($temporary, $this->file)) {
			if (is_file($temporary)) {
				unlink($temporary);
			}

			throw new RuntimeException("Could not write the maintenance file {$this->file}");
		}

		return $secret;
	}

	/** Turns maintenance mode off; returns whether it was on. */
	public function off(): bool
	{
		clearstatcache(true, $this->file);

		if (!is_file($this->file)) {
			return false;
		}

		if (!unlink($this->file)) {
			throw new RuntimeException("Could not remove the maintenance file {$this->file}");
		}

		return true;
	}
}
