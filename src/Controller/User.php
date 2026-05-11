<?php

declare(strict_types=1);

namespace Celemas\Cms\Controller;

use Celemas\Cms\Config;
use Celemas\Cms\Middleware\Permission;
use Celemas\Cms\Util\Password;
use Celemas\Core\Exception\HttpBadRequest;
use Celemas\Core\Request;
use Celemas\Quma\Database;

class User
{
	public function __construct(
		protected readonly Database $db,
	) {}

	#[Permission('authenticated')]
	public function list() {}

	#[Permission('authenticated')]
	public function profile(Request $request): array
	{
		$usr = $request->get('session')->authenticatedUserId();
		$user = $this->db->users->get(['usr' => $usr])->one();

		if ($user['data']) {
			$data = json_decode($user['data'], true);
			$name = $data['name'] ?? '';
		} else {
			$name = '';
		}

		return [
			'uid' => $user['uid'],
			'username' => $user['username'],
			'email' => $user['email'],
			'name' => $name,
		];
	}

	#[Permission('authenticated')]
	public function saveProfile(Request $request, Config $config): array
	{
		$data = $request->json();

		$usr = $request->get('session')->authenticatedUserId();
		$user = $this->db->users->get(['usr' => $usr])->one();
		$user['data'] = json_decode($user['data'], true);

		if ($data['uid'] !== $user['uid']) {
			throw new HttpBadRequest($request, payload: ['error' => 'Falsche uid']);
		}

		// E-Mail
		$email = trim($data['email'] ?? '');

		if ($email) {
			if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
				throw new HttpBadRequest($request, ['error' => 'Die E-Mail-Adresse ist ungültig!']);
			}
		} else {
			throw new HttpBadRequest($request, ['error' => 'Die E-Mail-Adresse muss angegeben werden!']);
		}

		if (strtolower($email) !== strtolower($user['email'])) {
			$existing = $this->db->users->get(['login' => $email])->first();

			if ($existing) {
				throw new HttpBadRequest($request, ['error' => 'Die E-Mail-Adresse ist bereits vergeben']);
			}
		}

		// User name
		$username = trim($data['username'] ?? '');

		if ($username) {
			if ($username !== ($user['username'] ?? null) && strlen($username) > 64) {
				throw new HttpBadRequest($request, ['error' => 'Der Benutzername ist zu lang']);
			}
		} else {
			$username = $user['username'] ?? null;
		}

		// Full name
		$name = trim($data['name'] ?? '');

		if ($name) {
			if ($name !== ($user['data']['name'] ?? '') && strlen($name) > 64) {
				throw new HttpBadRequest($request, ['error' => 'Der vollständige Name ist zu lang']);
			}
		} else {
			$name = $user['data']['name'] ?? null;
		}

		// Password
		$pw = trim($data['password'] ?? '');

		if ($pw) {
			$passwordUtil = Password::fromConfig($config);

			if (!$passwordUtil->strongEnough($pw)) {
				throw new HttpBadRequest($request, [
					'error' => 'Das Passwort ist zu schwach. Es sollte mindestens 12 Zeichen haben.',
				]);
			}

			if (trim($data['password']) !== trim($data['passwordRepeat'])) {
				throw new HttpBadRequest($request, ['error' => 'Die neuen Passwörder stimmen nicht überein']);
			}

			$pwHash = $passwordUtil->hash($pw);
		} else {
			$pwHash = $user['pwhash'];
		}

		$this->db->users->save([
			'usr' => $usr,
			'email' => $email,
			'username' => $username,
			'data' => ['name' => $name],
			'pwhash' => $pwHash,
			'editor' => $usr,
		])->run();

		return ['success' => true];
	}

	#[Permission('authenticated')]
	public function save(string $uid) {}

	#[Permission('authenticated')]
	public function create() {}
}
