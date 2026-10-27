<?php

declare(strict_types=1);

namespace Cosray\Controller\Panel;

use Celema\Container\Container;
use Celema\Core\Factory\Factory;
use Celema\Core\Request;
use Celema\Core\Response;
use Cosray\Auth as CmsAuth;
use Cosray\Config;
use Cosray\Security\Policy;
use Cosray\Security\RateLimiter;
use Cosray\User\PasswordResets;
use Cosray\Validation;
use Override;
use Psr\Log\LoggerInterface as Logger;

final class Login extends Panel
{
	/** Seconds a window of counted sign-in failures lasts. */
	public const int WINDOW = 900;

	/** Failed sign-ins per window from one address for one login. */
	public const int PER_LOGIN = 5;

	/** Failed sign-ins per window from one address, for any login. */
	public const int PER_ADDRESS = 30;

	public function __construct(
		Config $config,
		Container $container,
		Request $request,
		private readonly CmsAuth $auth,
		private readonly Logger $logger,
		private readonly PasswordResets $resets,
		private readonly RateLimiter $limiter,
	) {
		parent::__construct($config, $container, $request);
	}

	/** @return array<string, mixed>|Response */
	public function login(Factory $factory): array|Response
	{
		if ($this->hasPanelPermission()) {
			return $this->redirect($factory, $this->panelPath());
		}

		return $this->context([
			'next' => $this->sanitizedNext(),
			'login' => '',
			'rememberme' => false,
			'message' => null,
		]);
	}

	/** @return array<string, mixed>|Response */
	public function authenticate(Factory $factory): array|Response
	{
		$data = $this->formData();
		$shape = new Validation\Login();
		$result = $shape->validate($data);

		if (!$result->valid()) {
			return $this->context([
				'next' => $this->sanitizedNext($data['next'] ?? ''),
				'login' => (string) ($data['login'] ?? ''),
				'rememberme' => (bool) ($data['rememberme'] ?? false),
				'message' => $this->message(__('auth:missing-credentials')),
			]);
		}

		$values = $result->values();
		$ip = $this->auth->clientIp();
		$address = "sign-in:{$ip}";
		$login = $address . ':' . mb_strtolower($values['login']);
		// Counted before the password is checked, so concurrent guesses cannot slip past the limit.
		$wait = $this->limiter->attempt([$address => self::PER_ADDRESS, $login => self::PER_LOGIN], self::WINDOW);

		if ($wait > 0) {
			$this->logger->warning('Login from {ip} refused: too many failed attempts', ['ip' => $ip]);

			return $this->render(
				$factory,
				'login',
				$this->context([
					'next' => $this->sanitizedNext($data['next'] ?? ''),
					'login' => (string) ($data['login'] ?? ''),
					'rememberme' => (bool) ($data['rememberme'] ?? false),
					'message' => $this->tooManyAttempts($wait),
				]),
				429,
			)->header('Retry-After', (string) $wait);
		}

		$user = $this->auth->authenticate(
			$values['login'],
			$values['password'],
			$values['rememberme'],
			true,
		);

		if ($user === false) {
			return $this->context([
				'next' => $this->sanitizedNext($data['next'] ?? ''),
				'login' => (string) ($data['login'] ?? ''),
				'rememberme' => (bool) ($data['rememberme'] ?? false),
				'message' => $this->message(__('auth:invalid-credentials')),
			]);
		}

		$this->limiter->release($address);
		$this->limiter->clear($login);

		if (!$this->policy()->permits($user, 'panel')) {
			$this->auth->logout();
			$this->logger->notice('Panel login refused for {login} from {ip}: no panel permission', [
				'login' => $user->loginName(),
				'ip' => $this->auth->clientIp(),
			]);

			return $this->context([
				'next' => $this->sanitizedNext($data['next'] ?? ''),
				'login' => (string) ($data['login'] ?? ''),
				'rememberme' => false,
				'message' => $this->message(__('auth:no-panel-access')),
			]);
		}

		return $this->redirect($factory, $this->sanitizedNext($data['next'] ?? ''));
	}

	/**
	 * Logs in the user a one-time token was issued for, then follows `next`
	 * inside the panel: how an embedding application hands its user over.
	 */
	public function handoff(
		Factory $factory,
		#[\SensitiveParameter]
		string $token,
	): Response {
		$address = 'sign-in:' . $this->auth->clientIp();

		if ($this->limiter->attempt([$address => self::PER_ADDRESS], self::WINDOW) > 0) {
			return $this->redirect($factory, $this->panelPath() . '/login');
		}

		$user = $this->auth->authenticateByOneTimeToken($token, true);

		if ($user === false) {
			return $this->redirect($factory, $this->panelPath() . '/login');
		}

		$this->limiter->release($address);

		if (!$this->policy()->permits($user, 'panel')) {
			$this->auth->logout();

			return $this->redirect($factory, $this->panelPath() . '/login');
		}

		return $this->redirect($factory, $this->sanitizedNext());
	}

	/**
	 * Explains a form refused for a missing or outdated CSRF token, as when
	 * the page was opened before the session expired.
	 *
	 * @return array<string, mixed>
	 */
	public function expired(): array
	{
		return $this->context(['next' => $this->sanitizedNext()]);
	}

	public function logout(Factory $factory): Response
	{
		$user = $this->auth->user();
		$this->auth->logout();

		if ($user !== null) {
			$this->logger->info('Logout of {login} from {ip}', [
				'login' => $user->loginName(),
				'ip' => $this->auth->clientIp(),
			]);
		}

		return $this->redirect($factory, $this->panelPath() . '/login');
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array<string, mixed>
	 */
	#[Override]
	protected function context(array $data = []): array
	{
		return parent::context(['passwordReset' => $this->resets->available(), ...$data]);
	}

	/** @return array<array-key, mixed> */
	#[Override]
	protected function formData(): array
	{
		$data = parent::formData();
		$rememberme = $data['rememberme'] ?? false;
		$data['rememberme'] = in_array($rememberme, [true, 1, '1', 'true', 'on'], true);

		return $data;
	}

	private function hasPanelPermission(): bool
	{
		return $this->policy()->permits($this->auth->user(), 'panel');
	}

	private function policy(): Policy
	{
		return $this->container->instance(Policy::class);
	}

	private function sanitizedNext(string $next = ''): string
	{
		if ($next === '') {
			$next = $this->request->param('next', '');
		}

		if (!is_string($next)) {
			return $this->panelPath();
		}

		$next = trim($next);

		if ($next === '') {
			return $this->panelPath();
		}

		if (!str_starts_with($next, '/')) {
			return $this->panelPath();
		}

		if (preg_match('#^https?://#i', $next)) {
			return $this->panelPath();
		}

		if (!str_starts_with($next, $this->panelPath())) {
			return $this->panelPath();
		}

		return $next;
	}

	private function message(string $message): ?string
	{
		$message = trim($message);

		return $message === '' ? null : $message;
	}

	private function redirect(Factory $factory, string $target): Response
	{
		$response = Response::create($factory);

		if ($this->request->hasHeader('HX-Request')) {
			return $response
				->status(200)
				->header('HX-Redirect', $target);
		}

		return $response->redirect($target, 303);
	}
}
