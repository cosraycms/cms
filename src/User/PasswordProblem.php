<?php

declare(strict_types=1);

namespace Cosray\User;

/** Why PasswordPolicy refuses a password. */
enum PasswordProblem
{
	case Short;
	case Long;
	case Guessable;

	public function message(): string
	{
		return match ($this) {
			self::Short => __('user:error-password-short', ['min' => PasswordPolicy::MIN_LENGTH]),
			self::Long => __('user:error-password-long', ['max' => PasswordPolicy::MAX_LENGTH]),
			self::Guessable => __('user:error-password-guessable'),
		};
	}
}
