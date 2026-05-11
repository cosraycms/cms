<?php

declare(strict_types=1);

namespace Celemas\Cms\Util;

use Celemas\Cms\Config;

class Password
{
	public const DEFAULT_PASSWORD_ENTROPY = 40.0;

	public function __construct(
		protected string|int|null $algo = null,
		protected float $entropy = self::DEFAULT_PASSWORD_ENTROPY,
	) {
		if ($this->algo === null) {
			$this->algo = self::hasArgon2() ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
		}
	}

	public static function fromConfig(Config $config): self
	{
		$entropy = $config->password->entropy;
		$defaultAlgo = self::hasArgon2() ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
		$algo = $config->password->algorithm ?? $defaultAlgo;

		return new self($algo, $entropy);
	}

	public function strongEnough(#[\SensitiveParameter] string $password): bool
	{
		if (Strings::entropy($password) < $this->entropy) {
			return false;
		}

		return true;
	}

	public function valid(#[\SensitiveParameter] string $password, string $hash): bool
	{
		return password_verify($password, $hash);
	}

	public function hash(#[\SensitiveParameter] string $password): string
	{
		return password_hash($password, $this->algo);
	}

	public static function hasArgon2(): bool
	{
		return in_array('argon2id', password_algos(), true);
	}
}
