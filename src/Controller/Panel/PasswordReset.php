<?php

declare(strict_types=1);

namespace Cosray\Controller\Panel;

use Celema\Container\Container;
use Celema\Core\Exception\HttpNotFound;
use Celema\Core\Factory\Factory;
use Celema\Core\Request;
use Celema\Core\Response;
use Cosray\Auth;
use Cosray\Config;
use Cosray\Security\RateLimiter;
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

	/** Requests per window from one address. */
	public const int PER_ADDRESS = 5;

	/** Seconds a window of counted requests lasts. */
	public const int WINDOW = 900;

	public function __construct(
		Config $config,
		Container $container,
		Request $request,
		private readonly PasswordResets $resets,
		private readonly Auth $auth,
		private readonly Logger $logger,
		private readonly RateLimiter $limiter,
	) {
		parent::__construct($config, $container, $request);
	}

	/** @return array<string, mixed> */
	public function form(): array
	{
		$this->guard();

		return $this->page('request');
	}

	/** @return array<string, mixed>|Response */
	public function send(Factory $factory): array|Response
	{
		$this->guard();
		$login = $this->formData()['login'] ?? '';
		$login = is_string($login) ? trim($login) : '';

		if ($login === '' || mb_strlen($login) > self::MAX_LOGIN_LENGTH) {
			return $this->page('request', ['login' => $login, 'message' => __('auth:reset-missing-login')]);
		}

		$ip = $this->auth->clientIp();
		$wait = $this->limiter->attempt(["password-reset:{$ip}" => self::PER_ADDRESS], self::WINDOW);

		if ($wait > 0) {
			$this->logger->warning('Password reset request from {ip} refused: too many requests', ['ip' => $ip]);
			$page = $this->page('request', ['login' => $login, 'message' => $this->tooManyAttempts($wait)]);

			return $this->render($factory, 'password', $page, 429)->header('Retry-After', (string) $wait);
		}

		$locale = $this->request->get('panelLocale', null);
		$this->resets->request($login, $ip, is_string($locale) ? $locale : null);

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
			$length < self::MIN_PASSWORD_LENGTH => __('auth:reset-password-short', [
				'min' => self::MIN_PASSWORD_LENGTH,
			]),
			$length > self::MAX_PASSWORD_LENGTH => __('auth:reset-password-long', ['max' => self::MAX_PASSWORD_LENGTH]),
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
