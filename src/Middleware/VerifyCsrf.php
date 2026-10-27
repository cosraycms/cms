<?php

declare(strict_types=1);

namespace Cosray\Middleware;

use Celema\Core\Factory\Factory;
use Cosray\Auth;
use Cosray\Config;
use Cosray\Session;
use Cosray\Users;
use Override;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Psr\Log\LoggerInterface as Logger;

/**
 * Refuses unsafe requests that do not carry the session's CSRF token in the
 * `X-CSRF-Token` header or the `_token` form field. Requests with a bearer
 * token pass, as Auth then ignores their cookies.
 *
 * Panel scripts send the header and learn of a refusal through the
 * `cosray:csrf-expired` event; a plain form submit is sent to a page that
 * explains it and links back.
 */
final class VerifyCsrf implements Middleware
{
	public const string HEADER = 'X-CSRF-Token';
	public const string FIELD = '_token';
	public const string EVENT = 'cosray:csrf-expired';

	private const array SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

	public function __construct(
		private readonly Config $config,
		private readonly Users $users,
		private readonly Factory $factory,
		private readonly Logger $logger,
	) {}

	#[Override]
	public function process(Request $request, Handler $handler): Response
	{
		$auth = new Auth($request, $this->users, $this->config);

		if (in_array($request->getMethod(), self::SAFE_METHODS, true) || $auth->getAuthToken() !== '') {
			return $handler->handle($request);
		}

		$session = $request->getAttribute('session', null);

		if ($session instanceof Session && $session->csrf->verify(token: $this->token($request))) {
			return $handler->handle($request);
		}

		$this->logger->warning('Refused {method} {path} from {ip}: missing or invalid CSRF token', [
			'method' => $request->getMethod(),
			'path' => $request->getUri()->getPath(),
			'ip' => $auth->clientIp(),
		]);

		return $this->refused($request);
	}

	private function token(Request $request): string
	{
		$header = $request->getHeaderLine(self::HEADER);

		if ($header !== '') {
			return $header;
		}

		$body = $request->getParsedBody();
		$field = is_array($body) ? $body[self::FIELD] ?? null : null;

		return is_string($field) ? $field : '';
	}

	private function refused(Request $request): Response
	{
		$scripted = $request->hasHeader('HX-Request') || $request->hasHeader(self::HEADER);

		if (!$scripted && !str_contains($request->getHeaderLine('Accept'), 'application/json')) {
			return $this->factory
				->response(303)
				->withHeader('Location', $this->expiredUrl($request));
		}

		$message = __('auth:csrf-expired');
		$response = $this->factory
			->response(403)
			->withHeader('HX-Reswap', 'none')
			->withHeader('HX-Trigger', self::EVENT);

		if (str_contains($request->getHeaderLine('Accept'), 'application/json')) {
			return $response
				->withHeader('Content-Type', 'application/json')
				->withBody($this->factory->stream(json_encode(
					['ok' => false, 'error' => $message],
					JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE,
				)));
		}

		return $response
			->withHeader('Content-Type', 'text/plain; charset=utf-8')
			->withBody($this->factory->stream($message));
	}

	/** The page explaining the refusal, linking back to the page the form was on. */
	private function expiredUrl(Request $request): string
	{
		$url = rtrim($this->config->panel->path, '/') . '/expired';
		$referer = parse_url($request->getHeaderLine('Referer'));
		$host = $request->getUri()->getHost();

		if (!is_array($referer) || ($referer['host'] ?? null) !== $host || !isset($referer['path'])) {
			return $url;
		}

		$back = $referer['path'] . (isset($referer['query']) ? '?' . $referer['query'] : '');

		return $url . '?' . http_build_query(['next' => $back]);
	}
}
