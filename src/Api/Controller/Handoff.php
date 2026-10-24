<?php

declare(strict_types=1);

namespace Cosray\Api\Controller;

use Celema\Core\Exception\HttpBadRequest;
use Celema\Core\Exception\HttpForbidden;
use Celema\Core\Exception\HttpUnauthorized;
use Celema\Core\Request;
use Cosray\Api\Responses;
use Cosray\Config;
use Cosray\Security\Policy;
use Cosray\User;
use Cosray\Users;
use JsonException;
use Psr\Http\Message\ResponseInterface as Response;

/**
 * Exchanges the token of a panel user for a link that opens a panel session
 * once, within five minutes, for an application that frames the editor.
 */
final class Handoff
{
	public const int LIFETIME = 300;

	public function __construct(
		private readonly Responses $responses,
	) {}

	public function create(Request $request, Users $users, Policy $policy, Config $config): Response
	{
		$user = $request->get('user', null);

		if (!$user instanceof User) {
			throw new HttpUnauthorized($request, payload: ['message' => 'A handoff needs the token of a panel user.']);
		}

		if (!$policy->permits($user, 'panel')) {
			throw new HttpForbidden($request, payload: ['message' => 'The token\'s user has no panel access.']);
		}

		$next = $this->next($request);
		$token = $users->createOneTimeToken($user->id);
		$url = $config->app->urlPrefix . $config->panel->path . '/handoff/' . $token;

		if ($next !== null) {
			$url .= '?' . http_build_query(['next' => $config->panel->path . $next], '', '&', PHP_QUERY_RFC3986);
		}

		return $this->responses->json([
			'token' => $token,
			'url' => $url,
			'expires' => gmdate('Y-m-d\TH:i:s\Z', time() + self::LIFETIME),
		], 201);
	}

	/** The panel path to open, relative to the panel, like `/node/abc?from=embed`. */
	private function next(Request $request): ?string
	{
		$body = (string) $request->body();

		if (trim($body) === '') {
			return null;
		}

		try {
			$data = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			throw new HttpBadRequest($request, payload: ['message' => 'The body is not valid JSON.']);
		}

		$next = is_array($data) ? $data['next'] ?? null : null;

		if ($next !== null && (!is_string($next) || !str_starts_with($next, '/') || str_starts_with($next, '//'))) {
			throw new HttpBadRequest($request, payload: [
				'message' => '`next` is a path inside the panel, like /node/abc.',
			]);
		}

		return $next;
	}
}
