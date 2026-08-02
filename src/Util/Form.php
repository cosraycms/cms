<?php

declare(strict_types=1);

namespace Cosray\Util;

use Celema\Core\Request;

class Form
{
	/**
	 * The submitted body as an array.
	 *
	 * PHP only populates the parsed body for form-encoded POST requests, so
	 * PUT and DELETE handlers — and JSON posts — have to read the raw body
	 * themselves. Returns an empty array when there is nothing to parse.
	 *
	 * @return array<array-key, mixed>
	 */
	public static function body(Request $request): array
	{
		$data = $request->form() ?? [];

		if ($data !== []) {
			return $data;
		}

		$contentType = strtolower(trim(explode(';', $request->header('Content-Type'))[0]));

		if ($contentType === 'application/json') {
			$decoded = $request->json();

			return is_array($decoded) ? $decoded : [];
		}

		if ($contentType === 'application/x-www-form-urlencoded') {
			parse_str((string) $request->body(), $parsed);

			return $parsed;
		}

		return [];
	}
}
