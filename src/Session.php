<?php

declare(strict_types=1);

namespace Cosray;

use Celema\Session\Session as BaseSession;
use SessionHandlerInterface;

class Session extends BaseSession
{
	protected string $authCookie;

	public function __construct(
		array $options = [],
		string $name = '',
		?SessionHandlerInterface $handler = null,
	) {
		parent::__construct($options, $name, $handler);

		$this->authCookie = $name ? $name . '_auth' : 'cosray_auth';
	}

	public function setUser(int $userId): void
	{
		$_SESSION['user_id'] = $userId;
	}

	public function authenticatedUserId(): ?int
	{
		return $_SESSION['user_id'] ?? null;
	}

	public function remember(#[\SensitiveParameter] Token $token, int $expires): void
	{
		$value = $token->get();
		$_COOKIE[$this->authCookie] = $value;

		setcookie(
			$this->authCookie,
			$value,
			$this->rememberCookieOptions($expires),
		);
	}

	public function forgetRemembered(): void
	{
		unset($_COOKIE[$this->authCookie]);

		setcookie(
			$this->authCookie,
			'',
			$this->rememberCookieOptions(time() - (60 * 60 * 24)),
		);
	}

	public function getAuthToken(): ?string
	{
		return $_COOKIE[$this->authCookie] ?? null;
	}

	public function signalActivity(): void
	{
		$_SESSION['last_activity'] = time();
	}

	public function lastActivity(): ?int
	{
		return $_SESSION['last_activity'] ?? null;
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
