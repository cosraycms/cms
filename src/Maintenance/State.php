<?php

declare(strict_types=1);

namespace Cosray\Maintenance;

use SensitiveParameter;

/**
 * What the maintenance file holds. A file that is empty or not valid JSON,
 * like one made with `touch`, means writes are refused and nobody passes.
 */
final readonly class State
{
	public const int RETRY = 60;

	public function __construct(
		/** Whether reads are refused too, not only writes. */
		public bool $full = false,
		/** Seconds for clients to wait, sent as `Retry-After`. */
		public int $retry = self::RETRY,
		public ?string $message = null,
		public ?string $since = null,
		private ?string $secretHash = null,
	) {}

	/** @param array<mixed> $data */
	public static function fromArray(array $data): self
	{
		$retry = $data['retry'] ?? null;
		$message = $data['message'] ?? null;
		$since = $data['since'] ?? null;
		$secret = $data['secret'] ?? null;

		return new self(
			full: ($data['full'] ?? false) === true,
			retry: is_int($retry) && $retry > 0 ? $retry : self::RETRY,
			message: is_string($message) && $message !== '' ? $message : null,
			since: is_string($since) && $since !== '' ? $since : null,
			secretHash: is_string($secret) && $secret !== '' ? $secret : null,
		);
	}

	public static function hash(#[SensitiveParameter] string $secret): string
	{
		return hash('sha256', $secret);
	}

	/** Whether $secret is the one `maintenance:on` printed. */
	public function admits(#[SensitiveParameter] string $secret): bool
	{
		return $this->secretHash !== null && $secret !== '' && hash_equals($this->secretHash, self::hash($secret));
	}

	/** @return array{full: bool, retry: int, message: ?string, since: ?string, secret: ?string} */
	public function toArray(): array
	{
		return [
			'full' => $this->full,
			'retry' => $this->retry,
			'message' => $this->message,
			'since' => $this->since,
			'secret' => $this->secretHash,
		];
	}
}
