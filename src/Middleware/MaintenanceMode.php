<?php

declare(strict_types=1);

namespace Cosray\Middleware;

use Celema\Core\Factory\Factory;
use Cosray\Api\Responses;
use Cosray\Config;
use Cosray\Exception\UnderMaintenance;
use Cosray\Maintenance;
use Cosray\Maintenance\State;
use Override;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use SensitiveParameter;

/**
 * Refuses writes, or with `--full` every request, while maintenance mode is
 * on: with problem details below the API, otherwise through the site's error
 * page. A browser that opened the bypass link `maintenance:on` printed
 * passes, as do the paths in `maintenance.allow`.
 */
final class MaintenanceMode implements Middleware
{
	public const string COOKIE = 'cosray_maintenance';
	private const array SAFE = ['GET', 'HEAD', 'OPTIONS'];

	public function __construct(
		private readonly Config $config,
		private readonly Maintenance $maintenance,
		private readonly Factory $factory,
	) {}

	/** The path that sets the bypass cookie, below the panel. */
	public static function bypassPath(Config $config): string
	{
		return rtrim($config->panel->path, '/') . '/maintenance';
	}

	#[Override]
	public function process(Request $request, Handler $handler): Response
	{
		$state = $this->maintenance->state();

		if ($state === null) {
			return $handler->handle($request);
		}

		$path = $this->path($request);

		if ($path === self::bypassPath($this->config)) {
			$secret = $request->getQueryParams()['bypass'] ?? '';

			if (is_string($secret) && $state->admits($secret)) {
				return $this->admit($secret);
			}
		}

		$cookie = $request->getCookieParams()[self::COOKIE] ?? '';

		if (is_string($cookie) && $state->admits($cookie)) {
			return $handler->handle($request);
		}

		foreach ($this->config->maintenance->allow as $allowed) {
			if (self::below($path, $allowed)) {
				return $handler->handle($request);
			}
		}

		if (!$state->full && in_array(strtoupper($request->getMethod()), self::SAFE, true)) {
			return $handler->handle($request);
		}

		return $this->refuse($request, $path, $state);
	}

	private function refuse(Request $request, string $path, State $state): Response
	{
		$api = $this->config->api->path;

		if ($api !== null && self::below($path, $api)) {
			return new Responses($this->factory)->problem(
				503,
				$state->message,
				headers: ['Retry-After' => (string) $state->retry],
			);
		}

		throw new UnderMaintenance($request, [
			'message' => $state->message,
			'retry' => $state->retry,
			'since' => $state->since,
			'full' => $state->full,
		]);
	}

	private function admit(#[SensitiveParameter] string $secret): Response
	{
		$options = $this->config->session->options;
		$cookie = implode('; ', array_filter([
			self::COOKIE . '=' . $secret,
			'Path=' . (string) ($options['cookie_path'] ?? '/'),
			'SameSite=Lax',
			'HttpOnly',
			$options['cookie_secure'] ?? true ? 'Secure' : '',
		]));

		return $this->factory
			->response(303)
			->withHeader('Location', $this->config->app->urlPrefix . $this->config->panel->path)
			->withHeader('Set-Cookie', $cookie);
	}

	private function path(Request $request): string
	{
		$path = $request->getUri()->getPath();
		$prefix = rtrim($this->config->app->urlPrefix, '/');

		if ($prefix !== '' && ($path === $prefix || str_starts_with($path, $prefix . '/'))) {
			$path = substr($path, strlen($prefix));
		}

		return $path === '' ? '/' : $path;
	}

	private static function below(string $path, string $base): bool
	{
		$base = rtrim($base, '/');

		return $path === $base || str_starts_with($path, $base . '/');
	}
}
