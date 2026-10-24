<?php

declare(strict_types=1);

namespace Cosray\Api;

use Celema\Core\Factory\Factory;
use Psr\Http\Message\ResponseInterface as Response;

/** JSON bodies and RFC 9457 problem details, the only two things the API answers with. */
final class Responses
{
	public const string JSON = 'application/json';
	public const string PROBLEM = 'application/problem+json';

	private const array TITLES = [
		400 => 'Bad Request',
		401 => 'Unauthorized',
		403 => 'Forbidden',
		404 => 'Not Found',
		405 => 'Method Not Allowed',
		406 => 'Not Acceptable',
		409 => 'Conflict',
		410 => 'Gone',
		412 => 'Precondition Failed',
		413 => 'Content Too Large',
		415 => 'Unsupported Media Type',
		422 => 'Unprocessable Content',
		428 => 'Precondition Required',
		500 => 'Internal Server Error',
		503 => 'Service Unavailable',
	];

	public function __construct(
		private readonly Factory $factory,
	) {}

	/** @param array<string, string> $headers */
	public function json(mixed $data, int $status = 200, array $headers = []): Response
	{
		return $this->body(self::encode($data), $status, ['Content-Type' => self::JSON, ...$headers]);
	}

	/** @param array<string, string> $headers */
	public function empty(int $status = 204, array $headers = []): Response
	{
		return $this->body('', $status, $headers);
	}

	/**
	 * @param array<string, mixed> $extensions members next to the standard ones, like `errors`
	 * @param array<string, string> $headers
	 */
	public function problem(int $status, ?string $detail = null, array $extensions = [], array $headers = []): Response
	{
		$body = ['type' => 'about:blank', 'title' => self::TITLES[$status] ?? 'Error', 'status' => $status];

		if ($detail !== null && $detail !== '') {
			$body['detail'] = $detail;
		}

		return $this->body(
			self::encode([...$body, ...$extensions]),
			$status,
			['Content-Type' => self::PROBLEM, ...$headers],
		);
	}

	public static function encode(mixed $data): string
	{
		return json_encode(
			$data,
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR,
		);
	}

	/** @param array<string, string> $headers */
	private function body(string $body, int $status, array $headers): Response
	{
		$response = $this->factory->response($status);

		foreach ($headers as $name => $value) {
			$response = $response->withHeader($name, $value);
		}

		if ($body !== '') {
			$response->getBody()->write($body);
		}

		return $response;
	}
}
