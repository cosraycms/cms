<?php

declare(strict_types=1);

namespace Cosray;

use Celemas\Quma\Database;

class Users
{
	public function __construct(
		protected Database $db,
	) {}

	public function byLogin(string $login): ?User
	{
		return $this->getUserOrNull(
			$this->db->users->get([
				'login' => $login,
			])->first(),
		);
	}

	public function byAuthToken(#[\SensitiveParameter] string $token): ?User
	{
		return $this->getUserOrNull(
			$this->db->users->get([
				'token' => hash('sha256', $token),
			])->first(),
		);
	}

	public function byOneTimeToken(#[\SensitiveParameter] string $token): ?User
	{
		$hashedToken = hash('sha256', $token);

		$user = $this->getUserOrNull(
			$this->db->users->get([
				'onetimetoken' => $hashedToken,
			])->first(),
		);

		if ($user) {
			$this->db->users->removeOneTimeToken([
				'token' => $hashedToken,
			])->run();
		}

		return $user;
	}

	public function bySession(string $hash): ?User
	{
		return $this->getUserOrNull(
			$this->db->users->get([
				'sessionhash' => $hash,
			])->first(),
		);
	}

	public function byUid(string $uid): ?User
	{
		return $this->getUserOrNull(
			$this->db->users->get([
				'uid' => $uid,
			])->first(),
		);
	}

	public function byId(int $id): ?User
	{
		return $this->getUserOrNull(
			$this->db->users->get([
				'usr' => $id,
			])->first(),
		);
	}

	public function savePanelLocale(int $userId, ?string $locale): bool
	{
		return $this->db->users->savePanelLocale([
			'usr' => $userId,
			'locale' => $locale,
		])->run();
	}

	public function remember(string $hash, int $userId, string $expires): bool
	{
		return $this->db->users->remember([
			'hash' => $hash,
			'user' => $userId,
			'expires' => $expires,
		])->run();
	}

	public function forget(string $hash): bool
	{
		return $this->db->users->forget([
			'hash' => $hash,
		])->run();
	}

	public function createOneTimeToken(int $userId): string
	{
		$token = bin2hex(random_bytes(32));

		$this->db->users->saveOneTimeToken([
			'token' => hash('sha256', $token),
			'usr' => $userId,
		])->run();

		return $token;
	}

	public function removeOneTimeToken(#[\SensitiveParameter] string $token): void
	{
		$this->db->users->removeOneTimeToken([
			'token' => hash('sha256', $token),
		])->run();
	}

	protected function getUserOrNull(?array $data): ?User
	{
		if ($data) {
			return new User($data);
		}

		return null;
	}
}
