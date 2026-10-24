<?php

declare(strict_types=1);

namespace Cosray\Api\Controller;

use Celema\Core\Exception\HttpNotFound;
use Celema\Core\Request;
use Cosray\Api\Responses;
use Cosray\Config;
use Cosray\Locales;
use Cosray\Node\Types;
use Cosray\Security\NodeAccess;
use Cosray\User;
use Psr\Http\Message\ResponseInterface as Response;

final class Index
{
	public function __construct(
		private readonly Responses $responses,
	) {}

	/** What a client needs to start: the locales, the types it may read and who its token acts as. */
	public function index(
		Request $request,
		Config $config,
		Locales $locales,
		NodeAccess $access,
		Types $types,
	): Response {
		$user = $request->get('user', null);
		$user = $user instanceof User ? $user : null;
		$readable = $access->readable($user);
		$registered = $access->registered();
		$handles = [...$readable['any'], ...$readable['live']];
		sort($handles);

		return $this->responses->json([
			'name' => $config->app->name,
			'defaultLocale' => $locales->getDefault()->id,
			'locales' => array_values(array_map(static fn($locale): array => [
				'id' => $locale->id,
				'title' => $locale->title,
				'fallbacks' => $locale->fallbacks(),
			], iterator_to_array($locales))),
			'types' => array_map(static fn(string $handle): array => [
				'handle' => $handle,
				'label' => __((string) $types->get($registered[$handle], 'label')),
				'routable' => (bool) $types->get($registered[$handle], 'routable', false),
			], $handles),
			'user' => $user === null ? null : ['uid' => $user->uid, 'name' => $user->name ?? $user->loginName()],
		]);
	}

	public function missing(Request $request): never
	{
		throw new HttpNotFound($request, payload: ['message' => 'The API has no such endpoint.']);
	}
}
