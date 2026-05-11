<?php

declare(strict_types=1);

namespace Celemas\Cms\Tests\End2End;

use Celemas\Cms\Tests\End2EndTestCase;

/**
 * End-to-end tests for auth token flows.
 *
 * @internal
 *
 * @coversNothing
 */
final class AuthTest extends End2EndTestCase
{
	public function testAuthTokenExchangeRequiresValidToken(): void
	{
		$response = $this->makeRequest('GET', '/api/login/token', [
			'authToken' => 'invalid-token',
		]);

		$this->assertResponseStatus(401, $response);
		$payload = $this->assertJsonResponse($response, 401);
		$this->assertSame('token', $payload['loginType'] ?? null);
	}

	public function testAuthTokenExchangeReturnsOneTimeToken(): void
	{
		$this->authenticateAs('editor');

		$response = $this->makeRequest('GET', '/api/login/token', [
			'authToken' => $this->defaultAuthToken,
		]);

		$payload = $this->assertJsonResponse($response);
		$oneTimeToken = $payload['onetimeToken'] ?? null;
		$this->assertNotEmpty($oneTimeToken);
		$this->createdOneTimeTokens[] = hash('sha256', $oneTimeToken);
	}

	public function testTokenLoginRequiresValidToken(): void
	{
		$response = $this->makeRequest('POST', '/api/token-login', [
			'body' => ['token' => 'invalid-token'],
		]);

		$payload = $this->assertJsonResponse($response, 401);
		$this->assertSame('token', $payload['loginType'] ?? null);
	}

	public function testTokenLoginAcceptsOneTimeToken(): void
	{
		$this->authenticateAs('editor');

		$tokenResponse = $this->makeRequest('GET', '/api/login/token', [
			'authToken' => $this->defaultAuthToken,
		]);
		$tokenPayload = $this->assertJsonResponse($tokenResponse);
		$oneTimeToken = $tokenPayload['onetimeToken'] ?? null;
		$this->assertNotEmpty($oneTimeToken);
		$this->createdOneTimeTokens[] = hash('sha256', $oneTimeToken);

		$response = $this->makeRequest('POST', '/api/token-login', [
			'body' => ['token' => $oneTimeToken],
		]);

		$payload = $this->assertJsonResponse($response);
		$this->assertNotEmpty($payload['uid'] ?? null);
	}

	public function testLoginWithWrongCredentialsReturnsValidationPayload(): void
	{
		$response = $this->makeRequest('POST', '/api/login', [
			'body' => [
				'login' => 'nobody@example.com',
				'password' => 'wrong-password',
				'rememberme' => true,
			],
		]);

		$payload = $this->assertJsonResponse($response, 400);

		$this->assertSame('panel', $payload['loginType'] ?? null);
		$this->assertSame('nobody@example.com', $payload['login'] ?? null);
		$this->assertSame('wrong-password', $payload['password'] ?? null);
		$this->assertTrue($payload['rememberme'] ?? false);
	}

	public function testPermissionEnforcementReturnsUnauthorizedWithoutToken(): void
	{
		$response = $this->makeRequest('GET', '/api/nodes', [
			'query' => ['type' => 'test-article'],
		]);

		$this->assertResponseStatus(401, $response);
	}

	public function testPermissionEnforcementAllowsAuthenticatedRoles(): void
	{
		$superuserToken = $this->createAuthenticatedUser('superuser');
		$editorToken = $this->createAuthenticatedUser('editor');
		$this->createdAuthTokens[] = hash('sha256', $superuserToken);
		$this->createdAuthTokens[] = hash('sha256', $editorToken);

		$response = $this->makeRequest('GET', '/api/users', [
			'authToken' => $editorToken,
		]);
		$this->assertResponseOk($response);

		$response = $this->makeRequest('GET', '/api/users', [
			'authToken' => $superuserToken,
		]);
		$this->assertResponseOk($response);
	}
}
