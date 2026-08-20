<?php

declare(strict_types=1);

namespace Cosray\Migration;

use Cosray\Exception\RuntimeException;
use JsonException;

/**
 * Converts pre-structured-richtext HTML during one-shot migrations and imports.
 * Do not use this compatibility utility for request-time or newly authored content.
 */
final class LegacyRichtextHtmlConverter
{
	private const string SCRIPT = __DIR__ . '/../../resources/migration/legacy-richtext-html-converter.mjs';

	/**
	 * @param array<string, string> $units
	 * @return array<string, null|array<string, mixed>>
	 */
	public function convert(array $units): array
	{
		if ($units === []) {
			return [];
		}

		if (!is_file(self::SCRIPT)) {
			throw new RuntimeException(
				'The legacy richtext HTML converter is missing from the Cosray package.',
			);
		}

		$input = $this->tempFile('cosray-richtext-in-');
		$output = null;

		try {
			$output = $this->tempFile('cosray-richtext-out-');
			$this->writeInput($input, $units);
			$this->run($input, $output);

			return $this->readOutput($output, $units);
		} finally {
			$this->remove($input);

			if ($output !== null) {
				$this->remove($output);
			}
		}
	}

	private function tempFile(string $prefix): string
	{
		$file = tempnam(sys_get_temp_dir(), $prefix);

		if ($file === false) {
			throw new RuntimeException('Could not create a temporary richtext conversion file.');
		}

		return $file;
	}

	/** @param array<string, string> $units */
	private function writeInput(string $file, array $units): void
	{
		$handle = fopen($file, 'w');

		if ($handle === false) {
			throw new RuntimeException("Could not open richtext converter input: {$file}");
		}

		try {
			foreach ($units as $id => $html) {
				$line = json_encode(['id' => $id, 'html' => $html], JSON_THROW_ON_ERROR) . "\n";

				if (fwrite($handle, $line) === false) {
					throw new RuntimeException("Could not write richtext converter input: {$file}");
				}
			}
		} finally {
			fclose($handle);
		}
	}

	private function run(string $input, string $output): void
	{
		$process = proc_open(
			['node', self::SCRIPT],
			[0 => ['file', $input, 'r'], 1 => ['file', $output, 'w'], 2 => ['pipe', 'w']],
			$pipes,
			dirname(self::SCRIPT),
		);

		if (!is_resource($process)) {
			throw new RuntimeException(
				'Could not start the legacy richtext HTML converter. Is Node installed?',
			);
		}

		$stderr = (string) stream_get_contents($pipes[2]);
		fclose($pipes[2]);
		$status = proc_close($process);

		if ($status !== 0) {
			$detail = trim($stderr);
			$message = "Legacy richtext HTML converter failed with exit code {$status}.";

			throw new RuntimeException($detail === '' ? $message : "{$message} {$detail}");
		}
	}

	/**
	 * @param array<string, string> $units
	 * @return array<string, null|array<string, mixed>>
	 */
	private function readOutput(string $file, array $units): array
	{
		$lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

		if ($lines === false) {
			throw new RuntimeException("Could not read richtext converter output: {$file}");
		}

		$documents = [];

		foreach ($lines as $line) {
			try {
				$result = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
			} catch (JsonException $e) {
				throw new RuntimeException('The richtext converter returned invalid JSON.', previous: $e);
			}

			$id = is_array($result) ? $result['id'] ?? null : null;

			if (!is_string($id) || !array_key_exists($id, $units) || array_key_exists($id, $documents)) {
				throw new RuntimeException('The richtext converter returned an invalid unit id.');
			}

			if (is_string($result['error'] ?? null)) {
				throw new RuntimeException("Could not convert legacy richtext HTML {$id}: {$result['error']}");
			}

			$document = $result['doc'] ?? null;

			if ($document !== null && !is_array($document)) {
				throw new RuntimeException("The richtext converter returned an invalid document for {$id}.");
			}

			$documents[$id] = $document;
		}

		foreach (array_keys($units) as $id) {
			if (!array_key_exists($id, $documents)) {
				throw new RuntimeException("The richtext converter omitted unit {$id}.");
			}
		}

		return $documents;
	}

	private function remove(string $file): void
	{
		if (is_file($file)) {
			unlink($file);
		}
	}
}
