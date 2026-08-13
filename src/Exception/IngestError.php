<?php

declare(strict_types=1);

namespace Cosray\Exception;

/**
 * A rejected asset ingest. The exception message is plain English for CLI
 * callers and logs; `userMessage` is the translated one an HTTP caller returns.
 */
class IngestError extends RuntimeException
{
	public function __construct(
		string $message,
		public readonly string $userMessage,
		public readonly ?string $mime = null,
	) {
		parent::__construct($message);
	}

	public static function unknownFilename(): self
	{
		return new self('No usable filename', __('media:upload-failed'));
	}

	public static function tooLarge(int $bytes, int $maxSize): self
	{
		$size = number_format(($bytes / 1024) / 1024, 2, '.', '');
		$allowed = number_format(($maxSize / 1024) / 1024, 2, '.', '');

		return new self(
			"File too large: {$size} MiB ({$allowed} MiB allowed)",
			__('media:too-large', ['size' => $size, 'allowed' => $allowed]),
		);
	}

	public static function disallowedType(string $mime): self
	{
		return new self(
			"File type not allowed: {$mime}",
			__('media:disallowed-type', ['type' => $mime]),
			$mime,
		);
	}

	public static function wrongExtension(?string $ext, array $allowed, string $mime): self
	{
		$list = implode(', ', $allowed);

		return new self(
			"File extension '{$ext}' not allowed for {$mime} (allowed: {$list})",
			__('media:wrong-extension', ['ext' => (string) $ext, 'allowed' => $list]),
			$mime,
		);
	}

	public static function unsafeSvg(): self
	{
		return new self('SVG markup rejected by the sanitizer', __('media:unsafe-svg'));
	}
}
