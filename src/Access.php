<?php

declare(strict_types=1);

namespace Cosray;

use Celema\Core\Exception\HttpForbidden;
use Celema\Session\Session as BrowserSession;
use Cosray\Exception\AccessThrottled;
use Cosray\Exception\ReadDenied;
use Cosray\Exception\RuntimeException;
use Cosray\Node\Types;

final class Access
{
	public function __construct(
		private readonly Context $context,
	) {}

	public function allows(string $permission): bool
	{
		if ($permission === 'everyone') {
			return true;
		}

		$request = $this->context->request;
		if ($request === null) {
			return true;
		}

		$user = $request->get('user', null);
		if (
			$user instanceof User
			&& (
				$request->get('cms.management', false)
				&& $user->hasPermission('panel')
				|| $user->hasPermission($permission)
			)
		) {
			return true;
		}

		$hash = $this->hash($permission);
		$session = $request->get('session', null);
		if ($hash === null || !$session instanceof BrowserSession || !$session->active()) {
			return false;
		}

		$grant = $session->get('access.' . $permission, null);

		return (
			is_array($grant)
				&& ($grant['expires'] ?? 0) > time()
				&& hash_equals(hash('sha256', $hash), (string) ($grant['credential'] ?? ''))
		);
	}

	public function require(string $permission): void
	{
		if (!$this->allows($permission)) {
			throw new ReadDenied($permission);
		}
	}

	public function unlock(
		string $permission,
		#[\SensitiveParameter]
		string $password,
		#[\SensitiveParameter]
		string $token,
	): bool {
		$session = $this->session();
		if (!$session->csrf->verify('access.' . $permission, $token)) {
			throw new HttpForbidden();
		}

		$hash = $this->hash($permission);
		if ($hash === null) {
			throw new ReadDenied($permission);
		}

		$peer = $this->context->httpRequest()->unwrap()->getServerParams()['REMOTE_ADDR'] ?? 'unknown';
		$client = hash_hmac('sha256', (string) $peer, (string) $this->context->config->app->secret);
		$this->context->db->access->prune()->run();
		$attempt = $this->context->db->access->attempt(['permission' => $permission, 'client' => $client])->first();
		if ($attempt === null) {
			throw new AccessThrottled();
		}

		if (strlen($password) > 4096 || !password_verify($password, $hash)) {
			return false;
		}

		$this->context->db->access->success(['permission' => $permission, 'client' => $client])->run();
		$session->regenerate();
		$session->set('access.' . $permission, [
			'credential' => hash('sha256', $hash),
			'expires' => time() + (int) $this->context->config->session->options['gc_maxlifetime'],
		]);
		$session->csrf->refresh('access.' . $permission);

		return true;
	}

	public function logout(string $permission, #[\SensitiveParameter] string $token): void
	{
		$session = $this->session();
		if (!$session->csrf->verify('access.' . $permission, $token)) {
			throw new HttpForbidden();
		}

		$session->remove('access.' . $permission);
		$session->csrf->refresh('access.' . $permission);
	}

	public function token(string $permission): string
	{
		return $this->session()->csrf->token('access.' . $permission);
	}

	public function configured(string $permission): bool
	{
		return $this->hash($permission) !== null;
	}

	private function hash(string $permission): ?string
	{
		return $this->context->config->get('access.passwords', [])[$permission] ?? null;
	}

	public static function validate(Config $config): void
	{
		$passwords = $config->get('access.passwords', []);
		if (!is_array($passwords)) {
			throw new RuntimeException('access.passwords must map permission names to password hashes');
		}

		foreach ($passwords as $permission => $hash) {
			if (
				!is_string($permission)
				|| !preg_match('/^[a-z][a-z0-9-]{0,63}$/', $permission)
				|| in_array($permission, new Permissions()->get('superuser'), true)
				|| $permission === 'everyone'
				|| !is_string($hash)
				|| password_get_info($hash)['algo'] === null
			) {
				throw new RuntimeException('Invalid shared-password permission or password hash');
			}
		}

		if ($passwords !== [] && !$config->app->secret) {
			throw new RuntimeException('Shared-password access requires app.secret');
		}
	}

	private function session(): BrowserSession
	{
		$session = $this->context->httpRequest()->get('session', null);
		if (!$session instanceof BrowserSession || !$session->active()) {
			throw new RuntimeException('Shared-password access requires an active frontend session');
		}

		return $session;
	}

	public static function permission(string $class, Types $types): string
	{
		$permissions = $types->get($class, 'permission');

		return is_string($permissions) ? $permissions : $permissions['read'] ?? 'everyone';
	}
}
