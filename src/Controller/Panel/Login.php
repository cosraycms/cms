<?php

declare(strict_types=1);

namespace Celemas\Cms\Controller\Panel;

use Celemas\Cms\Auth as CmsAuth;
use Celemas\Cms\Config;
use Celemas\Cms\Validation;
use Celemas\Container\Container;
use Celemas\Core\Factory\Factory;
use Celemas\Core\Request;
use Celemas\Core\Response;

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
				'message' => $this->message(_('Please provide username and password')),
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
				'message' => $this->message(_('Invalid username or password')),
			]);
		}

		if (!$user->hasPermission('panel')) {
			$this->auth->logout();

			return $this->context([
				'next' => $this->sanitizedNext($data['next'] ?? ''),
				'login' => (string) ($data['login'] ?? ''),
				'rememberme' => false,
				'message' => $this->message(_('You are not allowed to access the panel')),
			]);
		}

		return $this->redirect($factory, $this->sanitizedNext($data['next'] ?? ''));
	}

	public function logout(Factory $factory): Response
	{
		$this->auth->logout();

		return $this->redirect($factory, $this->panelPath() . '/login');
	}

	private function formData(): array
	{
		$data = $this->request->form() ?? [];
		$contentType = strtolower(trim(explode(';', $this->request->header('Content-Type'))[0]));

		if ($data === [] && $contentType === 'application/json') {
			$decoded = $this->request->json();

			if (is_array($decoded)) {
				$data = $decoded;
			}
		}

		if ($data === [] && $contentType === 'application/x-www-form-urlencoded') {
			parse_str((string) $this->request->body(), $parsed);

			if (is_array($parsed)) {
				$data = $parsed;
			}
		}

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

	private function panelPath(): string
	{
		return self::PANEL_PATH;
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
