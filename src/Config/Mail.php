<?php

declare(strict_types=1);

namespace Cosray\Config;

use Cosray\Exception\RuntimeException;

final class Mail
{
	/** @var array<non-empty-string, non-empty-string>|null */
	private ?array $transportsCache = null;

	public function __construct(
		private readonly \Cosray\Config $config,
	) {}

	/**
	 * Symfony Mailer DSNs by name; the first one is the default.
	 *
	 * @var array<non-empty-string, non-empty-string>
	 */
	public array $transports {
		get => $this->transportsCache ??= self::transports($this->config->get('mail.transports'));
	}

	/** The sender of mails that name none, like `Example <noreply@example.com>`. */
	public ?string $from {
		get => self::string($this->config->get('mail.from'));
	}

	/**
	 * Addresses that receive every mail instead of its recipients, for
	 * staging and development.
	 *
	 * @var list<non-empty-string>
	 */
	public array $redirect {
		get => self::list($this->config->get('mail.redirect'));
	}

	/** @var list<non-empty-string> template directories, searched in order */
	public array $views {
		get => self::list($this->config->get('mail.views'))
			?: [
				rtrim($this->config->path->root, '/') . '/' . trim($this->config->path->views, '/') . '/mail',
			];
	}

	/** @return array<non-empty-string, non-empty-string> */
	private static function transports(mixed $transports): array
	{
		if (!is_array($transports)) {
			throw new RuntimeException('`mail.transports` takes DSNs by transport name.');
		}

		$valid = [];

		foreach ($transports as $name => $dsn) {
			if (!is_string($name) || $name === '' || !is_string($dsn) || $dsn === '') {
				throw new RuntimeException('`mail.transports` takes DSNs by transport name.');
			}

			$valid[$name] = $dsn;
		}

		return $valid;
	}

	/** @return list<non-empty-string> */
	private static function list(mixed $value): array
	{
		$values = is_string($value) ? explode(',', $value) : (array) $value;

		return array_values(array_filter(
			array_map(static fn(mixed $item): string => is_string($item) ? trim($item) : '', $values),
			static fn(string $item): bool => $item !== '',
		));
	}

	/** @return ?non-empty-string */
	private static function string(mixed $value): ?string
	{
		$value = is_string($value) ? trim($value) : '';

		return $value === '' ? null : $value;
	}
}
