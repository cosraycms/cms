<?php

declare(strict_types=1);

namespace Cosray\Controller\Panel;

use Celema\Container\Container;
use Celema\Core\Exception\HttpNotFound;
use Celema\Core\Request;
use Cosray\Auth;
use Cosray\Config;
use Cosray\User\PasswordResets;
use Psr\Log\LoggerInterface as Logger;

/**
 * Lets panel users who forgot their password choose a new one through a
 * link sent by mail. A request only queues the lookup and the mail, so its
 * answer and its work are the same for every login and the form does not
 * tell which logins exist. Without a mail transport and `app.url` the pages
 * do not exist.
 */
final class PasswordReset extends Panel
{
	private const int MAX_LOGIN_LENGTH = 254;
	private const int MIN_PASSWORD_LENGTH = 12;
	private const int MAX_PASSWORD_LENGTH = 512;

	public function __construct(
		Config $config,
		Container $container,
		Request $request,
		private readonly PasswordResets $resets,
		private readonly Auth $auth,
		private readonly Logger $logger,
	) {
		parent::__construct($config, $container, $request);
	}

	/** @return array<string, mixed> */
	public function form(): array
	{
		$this->guard();

		return $this->page('request');
	}

	/** @return array<string, mixed> */
	public function send(): array
	{
		$this->guard();
		$login = $this->formData()['login'] ?? '';
		$login = is_string($login) ? trim($login) : '';

		if ($login === '' || mb_strlen($login) > self::MAX_LOGIN_LENGTH) {
			return $this->page('request', ['login' => $login, 'message' => __('auth:reset-missing-login')]);
		}

		$locale = $this->request->get('panelLocale', null);
		$this->resets->request($login, $this->auth->clientIp(), is_string($locale) ? $locale : null);

		return $this->page('sent');
	}

	/** @return array<string, mixed> */
	public function choose(#[\SensitiveParameter] string $token): array
	{
		$this->guard();

		return $this->page($this->resets->user($token) === null ? 'invalid' : 'choose', ['token' => $token]);
	}

	/** @return array<string, mixed> */
	public function change(#[\SensitiveParameter] string $token): array
	{
		$this->guard();
		$form = $this->formData();
		$password = is_string($form['password'] ?? null) ? $form['password'] : '';
		$length = mb_strlen($password);
		$message = match (true) {
			$length < self::MIN_PASSWORD_LENGTH || $length > self::MAX_PASSWORD_LENGTH => __('user:password-help'),
			!hash_equals($password, (string) ($form['password_repeat'] ?? '')) => __('user:error-password-repeat'),
			default => null,
		};

		if ($message !== null) {
			$step = $this->resets->user($token) === null ? 'invalid' : 'choose';

			return $this->page($step, ['token' => $token, 'message' => $message]);
		}

		$user = $this->resets->complete($token, $password);

		if ($user === null) {
			return $this->page('invalid', ['token' => $token]);
		}

		$this->logger->notice('Password of {login} reset from {ip}', [
			'login' => $user->loginName(),
			'ip' => $this->auth->clientIp(),
		]);

		return $this->page('done');
	}

	private function guard(): void
	{
		if (!$this->resets->available()) {
			throw new HttpNotFound($this->request);
		}
	}

	/**
	 * @param 'request'|'sent'|'choose'|'invalid'|'done' $step
	 * @param array<string, mixed> $data
	 * @return array<string, mixed>
	 */
	private function page(string $step, array $data = []): array
	{
		return $this->context([
			'step' => $step,
			'login' => '',
			'token' => '',
			'message' => null,
			...$data,
		]);
	}
}
