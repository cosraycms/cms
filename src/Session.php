<?php

declare(strict_types=1);

namespace Cosray;

use Celemas\Session\Session as BaseSession;
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
		setcookie(
			$this->authCookie,
			$token->get(),
			$expires,
			'/',
		);
	}

	public function forgetRemembered(): void
	{
		setcookie(
			$this->authCookie,
			'',
			time() - (60 * 60 * 24),
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
}
