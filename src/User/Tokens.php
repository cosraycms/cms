<?php

declare(strict_types=1);

namespace Cosray\User;

use Celema\Quma\Database;
use Cosray\Actor;
use Cosray\Uid;
use Cosray\User;
use DateTimeImmutable;
use DateTimeInterface;
use ValueError;

/**
 * A user's API tokens. A token acts as its user, so the user's roles decide
 * what it may do; only its sha256 is stored.
 */
final class Tokens
{
	public const int MAX_NAME_LENGTH = 255;

	public function __construct(
		private readonly Database $db,
	) {}

	/**
	 * The token itself is returned only here; it cannot be read again.
	 *
	 * @return array{uid: string, token: string}
	 */
	public function create(User $user, string $name, Actor $actor, ?DateTimeInterface $expires = null): array
	{
		$name = trim($name);

		if ($name === '' || mb_strlen($name) > self::MAX_NAME_LENGTH) {
			throw new ValueError('A token needs a name of 1 to ' . self::MAX_NAME_LENGTH . ' characters');
		}

		if ($expires !== null && $expires <= new DateTimeImmutable()) {
			throw new ValueError('A token must expire in the future');
		}

		$token = bin2hex(random_bytes(32));
		$uid = new Uid(Uid::ALPHABET_LOWERCASE_WORD_SAFE, 13)->generate();

		$this->db->users->createToken([
			'token' => hash('sha256', $token),
			'uid' => $uid,
			'usr' => $user->id,
			'name' => $name,
			'expires' => $expires?->format(DATE_ATOM),
			'actor' => $actor->id,
		])->run();

		return ['uid' => $uid, 'token' => $token];
	}

	/** @return list<array{uid: string, name: string, created: string, expires: ?string, used: ?string}> */
	public function list(User $user): array
	{
		/** @var list<array{uid: string, name: string, created: string, expires: ?string, used: ?string}> */
		return $this->db->users->tokens(['usr' => $user->id])->all();
	}

	public function revoke(User $user, string $uid): bool
	{
		return $this->db->users->deleteToken(['uid' => $uid, 'usr' => $user->id])->len() > 0;
	}
}
