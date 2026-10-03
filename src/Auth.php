<?php

declare(strict_types=1);

namespace Cosray;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface as Logger;
use Psr\Log\NullLogger;
use RuntimeException;

class Auth
{
	/**
	 * An Argon2id hash with PHP's default cost, like stored passwords. Unknown
	 * logins are verified against it so they take as long as a wrong password
	 * and response times don't reveal which accounts exist.
	 */
	private const string UNKNOWN_ACCOUNT_HASH = '$argon2id$v=19$m=65536,t=4,p=1$LmdRei83enV3ZU80a3JCcA$nNwR27KMtv19PZECIhkY4mRM9Xp2bngKjiRHZdZ9lwM';

	public function __construct(
		protected Request $request,
		protected Users $users,
		protected Config $config,
		protected ?Session $session = null,
		protected Logger $logger = new NullLogger(),
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
			password_verify($password, self::UNKNOWN_ACCOUNT_HASH);
			// The submitted name stays out of the log: it may be a password
			// typed into the wrong field.
			$this->logger->warning('Login failed for an unknown account from {ip}', ['ip' => $this->clientIp()]);

			return false;
		}

		if (!password_verify($password, $user->password)) {
			$this->logger->warning('Login failed for {login} from {ip}', [
				'login' => $user->loginName(),
				'ip' => $this->clientIp(),
			]);

			return false;
		}

		if ($initSession) {
			$this->login($user, $remember);
		}

		$this->logger->info('Login succeeded for {login} from {ip}', [
			'login' => $user->loginName(),
			'ip' => $this->clientIp(),
		]);

		return $user;
	}

	public function authenticateByOneTimeToken(
		#[\SensitiveParameter]
		string $token,
		bool $initSession,
	): User|false {
		$user = $this->users->byOneTimeToken($token);

		if (!$user) {
			$this->logger->warning('One-time token login failed from {ip}', ['ip' => $this->clientIp()]);

			return false;
		}

		if ($initSession) {
			$this->login($user, false);
		}

		$this->logger->info('One-time token login succeeded for {login} from {ip}', [
			'login' => $user->loginName(),
			'ip' => $this->clientIp(),
		]);

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
			$user = $this->users->byId($userId);

			return $user !== null && $this->session->holds($user) ? $user : null;
		}

		$hash = $this->getSessionTokenHash();

		if ($hash) {
			$user = $this->users->bySession($hash);

			if ($user && !(strtotime($user->expires) < time())) {
				$this->startSession($user);
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

	/**
	 * The connecting address. Behind a reverse proxy this is the proxy's;
	 * forwarded headers are not trusted, as anyone can send them.
	 */
	public function clientIp(): string
	{
		$ip = $this->request->getServerParams()['REMOTE_ADDR'] ?? null;

		return is_string($ip) && $ip !== '' ? $ip : 'unknown';
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

	protected function login(User $user, bool $remember): void
	{
		$this->startSession($user);

		if ($remember) {
			$this->rememberUser($user->id);
		} else {
			$this->forgetRemembered();
		}
	}

	private function startSession(User $user): void
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
		$session->setUser($user);
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
