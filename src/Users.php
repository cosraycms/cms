<?php

declare(strict_types=1);

namespace Cosray;

use Celema\Quma\Database;
use Cosray\Exception\RuntimeException;
use Cosray\References\Scanner;
use Cosray\References\Sync;
use Cosray\User\Types;

class Users
{
	public function __construct(
		protected Database $db,
		protected Types $types = new Types(),
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

	/** @return list<User> live users, inactive ones included */
	public function list(?string $type, string $search, int $limit, int $offset): array
	{
		return array_map(
			$this->getUserOrNull(...),
			$this->db->users->list([...$this->filter($type, $search), 'limit' => $limit, 'offset' => $offset])->all(),
		);
	}

	public function count(?string $type, string $search): int
	{
		return (int) $this->db->users->count($this->filter($type, $search))->one()['total'];
	}

	/** Like `byUid()`, but finds a deactivated user too. */
	public function find(string $uid): ?User
	{
		return $this->getUserOrNull(
			$this->db->users->get(['uid' => $uid, 'inactiveAlso' => true])->first(),
		);
	}

	/**
	 * @param array{
	 *     username: ?string,
	 *     email: string,
	 *     name: ?string,
	 *     roles: list<string>,
	 *     active: bool,
	 *     panelLocale: ?string,
	 *     content: array<string, mixed>,
	 * } $values
	 */
	public function create(
		string $type,
		array $values,
		#[\SensitiveParameter]
		string $password,
		Actor $actor,
	): User {
		$uid = new Uid(Uid::ALPHABET_LOWERCASE_WORD_SAFE, 13)->generate();

		$this->db->users->create([
			'uid' => $uid,
			'type' => $type,
			'password' => password_hash($password, PASSWORD_ARGON2ID),
			'creator' => $actor->id,
			'editor' => $actor->id,
			...$this->row($values, []),
		])->run();
		$this->syncReferences($uid, $values['content']);

		return $this->find($uid) ?? throw new RuntimeException("User '{$uid}' was not created");
	}

	/** @param array{username: ?string, email: string, name: ?string, roles: list<string>, active: bool, panelLocale: ?string, content: array<string, mixed>} $values */
	public function update(User $user, array $values, Actor $actor): void
	{
		$this->db->users->update([
			'usr' => $user->id,
			'editor' => $actor->id,
			...$this->row($values, $user->data()),
		])->run();
		$this->syncReferences($user->uid, $values['content']);

		if (!$values['active']) {
			$this->db->users->forgetAll(['usr' => $user->id])->run();
		}
	}

	public function setPassword(
		User $user,
		#[\SensitiveParameter]
		string $password,
		Actor $actor,
	): void {
		$this->db->users->setPassword([
			'usr' => $user->id,
			'password' => password_hash($password, PASSWORD_ARGON2ID),
			'editor' => $actor->id,
		])->run();
		$this->db->users->forgetAll(['usr' => $user->id])->run();
	}

	public function delete(User $user, Actor $actor): void
	{
		$this->db->users->delete(['usr' => $user->id, 'editor' => $actor->id])->run();
		$this->db->users->forgetAll(['usr' => $user->id])->run();
		new Sync($this->db)->remove('user', $user->uid);
	}

	/** @return array{email: bool, username: bool} which logins another live user already holds */
	public function taken(string $email, ?string $username, ?User $except = null): array
	{
		$row = $this->db->users->taken([
			'email' => $email,
			'username' => $username,
			'usr' => $except->id ?? 0,
		])->one();

		return ['email' => (bool) $row['email'], 'username' => (bool) $row['username']];
	}

	public function isLastSuperuser(User $user): bool
	{
		return (
			$user->active
				&& in_array('superuser', $user->roles, true)
				&& (int) $this->db->users->otherSuperusers(['usr' => $user->id])->one()['total'] === 0
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

	/** @return array<string, ?string> */
	private function filter(?string $type, string $search): array
	{
		// Quma takes an empty argument list for positional, which a template
		// query refuses; an unset key keeps the list named.
		$filter = ['type' => null];

		if ($type !== null) {
			$filter['type'] = $type;
		}

		if ($search !== '') {
			$filter['search'] = '%' . addcslashes($search, '%_\\') . '%';
		}

		return $filter;
	}

	/**
	 * @param array<string, mixed> $values
	 * @param array<string, mixed> $stored keys of `users.data` the panel does not own survive a save
	 * @return array<string, mixed>
	 */
	private function row(array $values, array $stored): array
	{
		return [
			'username' => $values['username'],
			'email' => $values['email'],
			'roles' => json_encode(array_values($values['roles']), JSON_THROW_ON_ERROR),
			'active' => $values['active'],
			'panel_locale' => $values['panelLocale'],
			'data' => json_encode(
				[...$stored, 'name' => $values['name'], 'content' => (object) $values['content']],
				JSON_THROW_ON_ERROR,
			),
		];
	}

	/** @param array<string, mixed> $content */
	private function syncReferences(string $uid, array $content): void
	{
		new Sync($this->db)->replace('user', $uid, new Scanner()->scan($content));
	}

	protected function getUserOrNull(?array $data): ?User
	{
		if ($data) {
			return new ($this->types->class($data['type'] ?? Types::DEFAULT))($data);
		}

		return null;
	}
}
