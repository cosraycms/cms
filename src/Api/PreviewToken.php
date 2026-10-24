<?php

declare(strict_types=1);

namespace Cosray\Api;

use Cosray\Config;

/**
 * A signed, expiring grant to read one node's working copy through the API,
 * which the panel hands to a headless frontend's preview. It needs no
 * storage: the uid and the expiry travel in the token, signed with a key
 * derived from `app.secret`.
 */
final class PreviewToken
{
	public const int LIFETIME = 3600;

	private readonly string $key;

	public function __construct(
		#[\SensitiveParameter]
		string $secret,
	) {
		$this->key = hash_hmac('sha256', 'cosray-api-preview', $secret, true);
	}

	/** Null without `app.secret`, which signing needs. */
	public static function fromConfig(Config $config): ?self
	{
		$secret = $config->app->secret;

		return is_string($secret) && $secret !== '' ? new self($secret) : null;
	}

	public function issue(string $uid, ?int $expires = null): string
	{
		$payload = self::encode(json_encode(
			['uid' => $uid, 'exp' => $expires ?? (time() + self::LIFETIME)],
			JSON_THROW_ON_ERROR,
		));

		return $payload . '.' . $this->sign($payload);
	}

	/** The uid the token grants, or null when it is forged, malformed or expired. */
	public function uid(
		#[\SensitiveParameter]
		string $token,
		?int $now = null,
	): ?string {
		$parts = explode('.', $token);

		if (count($parts) !== 2 || !hash_equals($this->sign($parts[0]), $parts[1])) {
			return null;
		}

		$data = json_decode((string) base64_decode(strtr($parts[0], '-_', '+/'), true), true);

		if (!is_array($data) || !is_string($data['uid'] ?? null) || !is_int($data['exp'] ?? null)) {
			return null;
		}

		return $data['exp'] >= ($now ?? time()) ? $data['uid'] : null;
	}

	private function sign(string $payload): string
	{
		return self::encode(hash_hmac('sha256', $payload, $this->key, true));
	}

	private static function encode(string $bytes): string
	{
		return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
	}
}
