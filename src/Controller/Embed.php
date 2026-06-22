<?php

declare(strict_types=1);

namespace Cosray\Controller;

use Celemas\Core\Exception\HttpForbidden;
use Celemas\Core\Exception\HttpUnauthorized;
use Celemas\Core\Factory\Factory;
use Celemas\Core\Request;
use Celemas\Core\Response;
use Cosray\Auth;
use Cosray\Config;
use Cosray\Users;
use SensitiveParameter;

class Embed
{
	private const string PANEL_PATH = '/panel';

	public function __construct(
		protected readonly Request $request,
		protected readonly Config $config,
		protected readonly Factory $factory,
		protected readonly Users $users,
	) {}

	public function node(#[SensitiveParameter] string $token, string $type, string $node): Response
	{
		return $this->bootstrap($token, $this->embedPath($type, $node));
	}

	public function create(#[SensitiveParameter] string $token, string $type): Response
	{
		return $this->bootstrap($token, $this->embedCreatePath($type));
	}

	protected function bootstrap(#[SensitiveParameter] string $token, string $path): Response
	{
		$auth = new Auth(
			$this->request->unwrap(),
			$this->users,
			$this->config,
			$this->request->get('session', null),
		);
		$user = $auth->user();

		if ($user && $user->hasPermission('panel')) {
			$auth->invalidateOneTimeToken($token);

			return $this->redirect($path);
		}

		$user = $auth->authenticateByOneTimeToken($token, true);

		if (!$user) {
			throw new HttpUnauthorized($this->request);
		}

		if (!$user->hasPermission('panel')) {
			throw new HttpForbidden($this->request);
		}

		return $this->redirect($path);
	}

	protected function redirect(string $path): Response
	{
		$url = $this->panelBasePath() . $path;
		$query = $this->request->uri()->getQuery();

		if ($query !== '') {
			$url .= '?' . $query;
		}

		return Response::create($this->factory)->redirect($url);
	}

	protected function panelBasePath(): string
	{
		return self::PANEL_PATH;
	}

	protected function embedPath(string $type, string $node): string
	{
		return '/embed/node/' . rawurlencode($type) . '/' . rawurlencode($node);
	}

	protected function embedCreatePath(string $type): string
	{
		return '/embed/node/' . rawurlencode($type) . '/create';
	}
}
