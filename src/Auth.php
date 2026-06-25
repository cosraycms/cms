<?php

declare(strict_types=1);

namespace Cosray;

use Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException;

class Auth
{
	public function __construct(
		protected Request $request,
		protected Users $users,
		protected Config $config,
		protected ?Session $session = null,
	) {}

	public function logout(): void
	{
		if (!$this->session) {
			return;
		}

		$session = $this->session;
		$hash = $this->getSessionTokenHash();

		if ($hash) {
			$this->users->forget($hash);
			$session->forgetRemembered();
		}

		if ($session->active()) {
			$session->destroy();
		}
	}

	public function authenticate(
		string $login,
		#[\SensitiveParameter]
		string $password,
		bool $remember,
		bool $initSession,
	): User|false {
		$user = $this->users->byLogin($login);

		if (!$user) {
			return false;
		}

		if (password_verify($password, $user->password)) {
			if ($initSession) {
				$this->login($user->id, $remember);
			}

			return $user;
		}

		return false;
	}

	public function authenticateByOneTimeToken(
		#[\SensitiveParameter]
		string $token,
		bool $initSession,
	): User|false {
		$user = $this->users->byOneTimeToken($token);

		if (!$user) {
			return false;
		}

		if ($initSession) {
			$this->login($user->id, false);
		}

		return $user;
	}

	public function getOneTimeToken(
		#[\SensitiveParameter]
		string $token,
	): string|false {
		$user = $this->users->byAuthToken($token);

		if (!$user) {
			return false;
		}

		return $this->users->createOneTimeToken($user->id);
	}

	public function invalidateOneTimeToken(
		#[\SensitiveParameter]
		string $token,
	): void {
		$this->users->removeOneTimeToken($token);
	}

	public function user(): ?User
	{
		if (!$this->session) {
			return $this->userFromToken();
		}

		// Verify if user is logged in via cookie session
		$userId = $this->session->authenticatedUserId();

		if ($userId) {
			return $this->users->byId($userId);
		}

		$hash = $this->getSessionTokenHash();

		if ($hash) {
			$user = $this->users->bySession($hash);

			if ($user && !(strtotime($user->expires) < time())) {
				$this->startSession($user->id);
				$this->rememberUser($user->id);

				return $user;
			}

			$this->users->forget($hash);
			$this->session->forgetRemembered();
		}

		// Fall back to token auth if session auth failed
		return $this->userFromToken();
	}

	protected function userFromToken(): ?User
	{
		$authToken = $this->getAuthToken();

		if ($authToken) {
			return $this->users->byAuthToken($authToken);
		}

		return null;
	}

	public function permissions(): array
	{
		$user = $this->user();

		if ($user === null) {
			return [];
		}

		return $user->permissions();
	}

	public function getAuthToken(): string
	{
		$authToken = '';
		$bearer = $this->request->getHeaderLine('Authentication');

		if (preg_match('/Bearer\s(\S+)/', $bearer, $matches)) {
			$authToken = $matches[1];
		}

		return $authToken;
	}

	protected function remember(int $userId): RememberDetails
	{
		$token = new Token($this->config->app->secret);
		$expires = time() + $this->config->auth->rememberLifetime;

		$remembered = $this->users->remember(
			$token->hash(),
			$userId,
			date(DATE_ATOM, $expires),
		);

		if ($remembered) {
			return new RememberDetails($token, $expires);
		}

		throw new RuntimeException('Could not remember user');
	}

	protected function login(int $userId, bool $remember): void
	{
		$this->startSession($userId);

		if ($remember) {
			$this->rememberUser($userId);
		} else {
			$this->forgetRemembered();
		}
	}

	private function startSession(int $userId): void
	{
		$session = $this->session;

		if (!$session) {
			throw new RuntimeException('Cannot initialize auth session without session service');
		}

		if (!$session->active()) {
			$session->start();
		}

		// Regenerate the session id before setting the user id
		// to mitigate session fixation attack.
		$session->regenerate();
		$session->setUser($userId);
	}

	private function rememberUser(int $userId): void
	{
		if (!$this->session) {
			throw new RuntimeException('Cannot remember user without session service');
		}

		$details = $this->remember($userId);

		$this->session->remember(
			$details->token,
			$details->expires,
		);
	}

	private function forgetRemembered(): void
	{
		if (!$this->session) {
			return;
		}

		$hash = $this->getSessionTokenHash();

		if ($hash !== null) {
			$this->users->forget($hash);
			$this->session->forgetRemembered();
		}
	}

	protected function getSessionTokenHash(): ?string
	{
		if (!$this->session) {
			return null;
		}

		$token = $this->session->getAuthToken();

		if ($token) {
			return new Token($this->config->app->secret, $token)->hash();
		}

		return null;
	}
}
