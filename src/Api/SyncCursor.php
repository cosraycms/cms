<?php

declare(strict_types=1);

namespace Cosray\Api;

use Celema\Core\Exception\HttpBadRequest;
use DateTimeImmutable;
use DateTimeZone;
use JsonException;

/**
 * A position in the change order, which runs by change time, to the
 * microsecond, and then by uid. A client goes on from the token it got, so
 * nodes changed at the same time are neither skipped nor repeated.
 */
final readonly class SyncCursor
{
	private const string FORMAT = 'Y-m-d\\TH:i:s.u\\Z';

	private function __construct(
		public string $changed,
		public ?string $uid,
	) {}

	/** Before the nodes changed after this time. */
	public static function since(string $time): self
	{
		return new self(self::format($time), null);
	}

	/** After the node changed at this time, as the database gives it, with this uid. */
	public static function after(string $changed, string $uid): self
	{
		return new self(self::format($changed), $uid);
	}

	public static function fromToken(mixed $cursor): self
	{
		$invalid = new HttpBadRequest(payload: ['message' => '`cursor` is the token of an earlier sync response.']);
		$json = is_string($cursor) ? base64_decode(strtr($cursor, '-_', '+/'), true) : false;

		try {
			$position = $json === false ? null : json_decode($json, true, 2, JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			throw $invalid;
		}

		if (!is_array($position) || !array_is_list($position) || count($position) !== 2) {
			throw $invalid;
		}

		[$changed, $uid] = $position;

		$time = is_string($changed) ? DateTimeImmutable::createFromFormat(self::FORMAT, $changed, self::utc()) : false;

		if ($time === false || $time->format(self::FORMAT) !== $changed || !is_string($uid) && $uid !== null) {
			throw $invalid;
		}

		return new self($changed, $uid);
	}

	public function token(): string
	{
		return rtrim(
			strtr(base64_encode(json_encode([$this->changed, $this->uid], JSON_THROW_ON_ERROR)), '+/', '-_'),
			'=',
		);
	}

	private static function format(string $time): string
	{
		return new DateTimeImmutable($time)
			->setTimezone(self::utc())
			->format(self::FORMAT);
	}

	private static function utc(): DateTimeZone
	{
		return new DateTimeZone('UTC');
	}

	/** @return array<string, mixed> a filter for the nodes after this position */
	public function filter(): array
	{
		if ($this->uid === null) {
			return ['changed' => ['gt' => $this->changed]];
		}

		return [
			'or' => [
				['changed' => ['gt' => $this->changed]],
				['changed' => $this->changed, 'uid' => ['gt' => $this->uid]],
			],
		];
	}
}
