<?php

declare(strict_types=1);

namespace Cosray;

use Celema\Session\Session as BaseSession;
use SessionHandlerInterface;

/**
 * The CMS session: the signed-in user and the remember-me cookie. Session
 * data goes through celema/session; the remember-me cookie is read from
 * the request's cookies, not from `$_COOKIE`.
 */
class Session extends BaseSession
{
	protected string $authCookie;
	protected ?string $authToken;

	/** @param array<array-key, mixed> $cookies The request's cookies. */
	public function __construct(
		array $options = [],
		string $name = '',
		?SessionHandlerInterface $handler = null,
		array $cookies = [],
	) {
		parent::__construct($options, $name, $handler);

		$this->authCookie = $name ? $name . '_auth' : 'cosray_auth';
		$token = $cookies[$this->authCookie] ?? null;
		$this->authToken = is_string($token) && $token !== '' ? $token : null;
	}

	public function setUser(User $user): void
	{
		$this->set('user_id', $user->id);
		$this->set('user_stamp', self::stamp($user));
	}

	/**
	 * A session opened before the user's password last changed no longer
	 * counts. One without a stamp predates the stamp and is left alone.
	 */
	public function holds(User $user): bool
	{
		$stamp = $this->read('user_stamp');

		return !is_string($stamp) || hash_equals($stamp, self::stamp($user));
	}

	public function authenticatedUserId(): ?int
	{
		$id = $this->read('user_id');

		return is_int($id) ? $id : null;
	}

	public function remember(#[\SensitiveParameter] Token $token, int $expires): void
	{
		$value = $token->get();
		// Later reads in the same request see the new token.
		$this->authToken = $value;

		setcookie(
			$this->authCookie,
			$value,
			$this->rememberCookieOptions($expires),
		);
	}

	public function forgetRemembered(): void
	{
		$this->authToken = null;

		setcookie(
			$this->authCookie,
			'',
			$this->rememberCookieOptions(time() - (60 * 60 * 24)),
		);
	}

	public function getAuthToken(): ?string
	{
		return $this->authToken;
	}

	public function signalActivity(): void
	{
		$this->set('last_activity', time());
	}

	public function lastActivity(): ?int
	{
		$time = $this->read('last_activity');

		return is_int($time) ? $time : null;
	}

	/**
	 * A value of the current session, or null when the session is not
	 * active, for example after it was destroyed by a logout.
	 *
	 * @param non-empty-string $key
	 */
	private function read(string $key): mixed
	{
		return $this->active() ? $this->get($key, null) : null;
	}

	private static function stamp(User $user): string
	{
		return substr(hash('sha256', $user->password), 0, 32);
	}

	/** @return array{expires: int, path: string, domain?: string, secure: bool, httponly: bool, samesite: string, partitioned?: bool} */
	private function rememberCookieOptions(int $expires): array
	{
		$options = [
			'expires' => $expires,
			'path' => (string) ($this->options['cookie_path'] ?? '/'),
			'secure' => (bool) ($this->options['cookie_secure'] ?? true),
			'httponly' => (bool) ($this->options['cookie_httponly'] ?? true),
			'samesite' => (string) ($this->options['cookie_samesite'] ?? 'Lax'),
		];

		$domain = (string) ($this->options['cookie_domain'] ?? '');
		if ($domain !== '') {
			$options['domain'] = $domain;
		}

		if (isset($this->options['cookie_partitioned'])) {
			$options['partitioned'] = (bool) $this->options['cookie_partitioned'];
		}

		return $options;
	}
}
