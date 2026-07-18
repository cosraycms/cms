<?php

declare(strict_types=1);

namespace Cosray\Controller\Panel;

use Celema\Container\Container;
use Celema\Core\Factory\Factory;
use Celema\Core\Request;
use Celema\Core\Response;
use Cosray\Auth as CmsAuth;
use Cosray\Config;
use Cosray\Validation;

final class Login extends Panel
{
	public function __construct(
		Config $config,
		Container $container,
		Request $request,
		private readonly CmsAuth $auth,
	) {
		parent::__construct($config, $container, $request);
	}

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

		if (!$user->hasPermission('panel')) {
			$this->auth->logout();

			return $this->context([
				'next' => $this->sanitizedNext($data['next'] ?? ''),
				'login' => (string) ($data['login'] ?? ''),
				'rememberme' => false,
				'message' => $this->message(__('auth:no-panel-access')),
			]);
		}

		return $this->redirect($factory, $this->sanitizedNext($data['next'] ?? ''));
	}

	public function logout(Factory $factory): Response
	{
		$this->auth->logout();

		return $this->redirect($factory, $this->panelPath() . '/login');
	}

	protected function formData(): array
	{
		$data = parent::formData();
		$rememberme = $data['rememberme'] ?? false;
		$data['rememberme'] = in_array($rememberme, [true, 1, '1', 'true', 'on'], true);

		return $data;
	}

	private function hasPanelPermission(): bool
	{
		$user = $this->auth->user();

		if ($user === null) {
			return false;
		}

		return $user->hasPermission('panel');
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
