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

	/** Given a user, the token also shows that user's autosave on top. */
	public function issue(string $uid, ?int $expires = null, ?int $user = null): string
	{
		$payload = self::encode(json_encode(
			[
				'uid' => $uid,
				'exp' => $expires ?? (time() + self::LIFETIME),
				...($user === null ? [] : ['usr' => $user]),
			],
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
		return $this->claims($token, $now)['uid'] ?? null;
	}

	/** The user whose autosave the token shows, if it names one. */
	public function user(
		#[\SensitiveParameter]
		string $token,
		?int $now = null,
	): ?int {
		return $this->claims($token, $now)['usr'] ?? null;
	}

	/** @return ?array{uid: string, usr?: int} */
	private function claims(
		#[\SensitiveParameter]
		string $token,
		?int $now,
	): ?array {
		$parts = explode('.', $token);

		if (count($parts) !== 2 || !hash_equals($this->sign($parts[0]), $parts[1])) {
			return null;
		}

		$data = json_decode((string) base64_decode(strtr($parts[0], '-_', '+/'), true), true);

		if (!is_array($data) || !is_string($data['uid'] ?? null) || !is_int($data['exp'] ?? null)) {
			return null;
		}

		if ($data['exp'] < ($now ?? time())) {
			return null;
		}

		return [
			'uid' => $data['uid'],
			...(is_int($data['usr'] ?? null) ? ['usr' => $data['usr']] : []),
		];
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
