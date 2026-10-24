<?php

declare(strict_types=1);

namespace Cosray\Controller\Panel;

use Celema\Container\Container;
use Celema\Core\Factory\Factory;
use Celema\Core\Request;
use Celema\Core\Response;
use Cosray\Config;
use Cosray\User\Tokens as TokenStore;
use DateTimeImmutable;
use Throwable;
use ValueError;

/** The API tokens of the signed-in user, who manages only their own. */
final class Tokens extends Panel
{
	protected const string AREA = 'profile';

	public function __construct(
		Config $config,
		Container $container,
		Request $request,
		private readonly TokenStore $tokens,
	) {
		parent::__construct($config, $container, $request);
	}

	/** @return array<string, mixed> */
	public function index(): array
	{
		return $this->page();
	}

	/** @return array<string, mixed> */
	public function store(): array
	{
		$data = $this->formData();
		$name = is_string($data['name'] ?? null) ? $data['name'] : '';
		$expires = is_string($data['expires'] ?? null) ? trim($data['expires']) : '';

		try {
			$expiry = $expires === ''
				? null
				: new DateTimeImmutable($expires . ' 23:59:59', $this->config->app->timezone);
		} catch (Throwable) {
			return $this->page(error: __('tokens:error-expires'), name: $name);
		}

		try {
			$created = $this->tokens->create($this->currentUser(), $name, $this->actor(), $expiry);
		} catch (ValueError) {
			return $this->page(
				error: $expiry === null ? __('tokens:error-name') : __('tokens:error-invalid'),
				name: $name,
			);
		}

		return $this->page(created: $created['token']);
	}

	public function revoke(Factory $factory, string $uid): Response
	{
		$this->tokens->revoke($this->currentUser(), $uid);

		return Response::create($factory)->redirect($this->panelPath() . '/profile/tokens', 303);
	}

	/** @return array<string, mixed> */
	private function page(?string $created = null, ?string $error = null, string $name = ''): array
	{
		$timezone = $this->config->app->timezone;
		$date = static fn(?string $value): ?string => $value === null
			? null
			: new DateTimeImmutable($value)
				->setTimezone($timezone)
				->format('Y-m-d H:i');

		return $this->context([
			'created' => $created,
			'error' => $error,
			'name' => $name,
			'tokensUrl' => $this->panelPath() . '/profile/tokens',
			'profileUrl' => $this->panelPath() . '/profile',
			'tokens' => array_map(fn(array $entry): array => [
				'uid' => $entry['uid'],
				'name' => $entry['name'],
				'created' => $date($entry['created']),
				'expires' => $date($entry['expires']),
				'used' => $date($entry['used']),
				'revokeUrl' => $this->panelPath() . '/profile/tokens/' . rawurlencode($entry['uid']) . '/delete',
			], $this->tokens->list($this->currentUser())),
		]);
	}
}
