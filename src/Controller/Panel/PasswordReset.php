<?php

declare(strict_types=1);

namespace Cosray\Controller\Panel;

use Celema\Container\Container;
use Celema\Core\Exception\HttpNotFound;
use Celema\Core\Request;
use Celema\Verba\Verba;
use Closure;
use Cosray\Auth;
use Cosray\Config;
use Cosray\Locales;
use Cosray\Mailer;
use Cosray\Middleware\PanelLocale;
use Cosray\Security\Policy;
use Cosray\User;
use Cosray\User\PasswordResets;
use Cosray\Users;
use Psr\Log\LoggerInterface as Logger;
use Symfony\Component\Mime\Address;

/**
 * Lets panel users who forgot their password choose a new one through a
 * link sent by mail. Every answer to a request reads the same, so the form
 * does not tell which logins exist. Without a mail transport and `app.url`
 * the pages do not exist.
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
		private readonly Users $users,
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
	public function send(Mailer $mailer): array
	{
		$this->guard();
		$login = $this->formData()['login'] ?? '';
		$login = is_string($login) ? trim($login) : '';

		if ($login === '' || mb_strlen($login) > self::MAX_LOGIN_LENGTH) {
			return $this->page('request', ['login' => $login, 'message' => __('auth:reset-missing-login')]);
		}

		$user = $this->users->byLogin($login);
		$token = $user !== null && $this->policy()->permits($user, 'panel') ? $this->resets->issue($user) : null;

		if ($user !== null && $token !== null) {
			$this->inLocaleOf($user, fn(): int => $mailer->send(
				'cosray/password-reset',
				to: new Address($user->email, $user->name ?? ''),
				data: [
					'name' => $user->name ?? $user->loginName(),
					'site' => $this->config->app->name,
					'url' => $this->resets->url($token),
				],
			));
			$this->logger->notice('Password reset link sent to {login} for a request from {ip}', [
				'login' => $user->loginName(),
				'ip' => $this->auth->clientIp(),
			]);
		}

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

	/**
	 * Runs the callback in the panel language the user chose, if they chose
	 * one, and in that of the request otherwise.
	 *
	 * @template T
	 * @param Closure(): T $callback
	 * @return T
	 */
	private function inLocaleOf(User $user, Closure $callback): mixed
	{
		$locales = $this->request->get('locales', null);

		if (
			!$locales instanceof Locales
			|| $user->panelLocale === null
			|| !in_array($user->panelLocale, $locales->panelLocales(), true)
		) {
			return $callback();
		}

		$previous = Verba::translator();
		Verba::activate(new PanelLocale($this->config)->translator($locales, $user->panelLocale));

		try {
			return $callback();
		} finally {
			if ($previous !== null) {
				Verba::activate($previous);
			} else {
				Verba::deactivate();
			}
		}
	}

	private function policy(): Policy
	{
		return $this->container->instance(Policy::class);
	}
}
