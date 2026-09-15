<?php

declare(strict_types=1);

namespace Cosray\Controller;

use Celema\Core\Exception\HttpForbidden;
use Celema\Core\Response;
use Cosray\Context;
use Cosray\Exception\AccessThrottled;
use Cosray\Util\Form;
use Cosray\View\Boiler\Renderer;

final class Access
{
	public function __construct(
		private readonly Context $context,
	) {}

	public function challenge(string $permission): Response
	{
		$request = $this->context->httpRequest();
		$response = Response::create($this->context->factory)->header('Cache-Control', 'private, no-store');
		if (!$this->context->access()->configured($permission) || $request->method() !== 'GET') {
			return $response->status(403);
		}
		if ($request->get('isXhr', false)) {
			return $response->json(['error' => 'authentication_required'], 401);
		}

		$target = $request->uri()->getPath();
		$query = $request->uri()->getQuery();
		if ($query !== '') {
			$target .= '?' . $query;
		}

		return $response->redirect($this->url($permission) . '?' . http_build_query(['next' => $target]), 303);
	}

	public function login(string $permission): Response
	{
		$access = $this->context->access();
		$request = $this->context->httpRequest();
		if (!$access->configured($permission)) {
			return Response::create($this->context->factory)->status(404)->header('Cache-Control', 'private, no-store');
		}

		$data = $request->method() === 'POST' ? Form::body($request) : [];
		$target = $this->target($data['next'] ?? $request->param('next', ''));
		$message = null;
		$status = 200;
		if ($request->method() === 'POST') {
			try {
				$password = $data['password'] ?? '';
				$token = $data['_token'] ?? '';
				if (!is_string($password) || !is_string($token)) {
					throw new HttpForbidden();
				}
				if ($access->unlock($permission, $password, $token)) {
					return Response::create($this->context->factory)
						->header('Cache-Control', 'private, no-store')
						->redirect($target, 303);
				}
				$message = __('Incorrect password.');
				$status = 401;
			} catch (AccessThrottled) {
				$message = __('Too many attempts. Please try again in 15 minutes.');
				$status = 429;
			}
		}

		$config = $this->context->config;
		$html = new Renderer([
			$config->path->root . '/' . ltrim($config->path->views, '/'),
			dirname(__DIR__, 2) . '/resources/views',
		])->render('access', [
			'permission' => $permission,
			'locale' => $this->context->localeId(),
			'action' => $this->url($permission),
			'next' => $target,
			'token' => $access->token($permission),
			'message' => $message,
		]);

		$response = Response::create($this->context->factory)
			->status($status)
			->header('Content-Type', 'text/html; charset=utf-8')
			->header('Cache-Control', 'private, no-store')
			->header('X-Robots-Tag', 'noindex, nofollow')
			->body($html);
		if ($status === 429) {
			$response->header('Retry-After', '900');
		}

		return $response;
	}

	public function logout(string $permission): Response
	{
		$data = Form::body($this->context->httpRequest());
		$token = $data['_token'] ?? '';
		if (!is_string($token)) {
			throw new HttpForbidden();
		}
		$this->context->access()->logout($permission, $token);

		return Response::create($this->context->factory)
			->header('Cache-Control', 'private, no-store')
			->redirect($this->target($data['next'] ?? ''), 303);
	}

	private function url(string $permission): string
	{
		return $this->context->config->app->urlPrefix . '/access/' . rawurlencode($permission);
	}

	private function target(mixed $target): string
	{
		$prefix = $this->context->config->app->urlPrefix;
		if (!is_string($target)) {
			return $prefix . '/';
		}

		$decoded = rawurldecode($target);
		if (
			!str_starts_with($decoded, $prefix . '/')
			|| str_starts_with($decoded, '//')
			|| preg_match('/[\\\\\x00-\x20\x7f]/', $decoded)
		) {
			return $prefix . '/';
		}

		return $target;
	}
}
