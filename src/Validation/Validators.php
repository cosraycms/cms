<?php

declare(strict_types=1);

namespace Celemas\Cms\Validation;

use Celemas\Sire\Contract\ValidatesEmpty;
use Celemas\Sire\Contract\Validator;
use Celemas\Sire\Contract\Value;
use Celemas\Sire\ValidatorRegistry;
use Override;

final class Validators
{
	public static function registry(): ValidatorRegistry
	{
		return ValidatorRegistry::withDefaults()->withMany([
			'minitems' => self::minItems(),
			'maxitems' => self::maxItems(),
		]);
	}

	private static function minItems(): Validator
	{
		return new class implements ValidatesEmpty {
			public string $message = 'Has fewer than the minimum number of %4$s items';

			#[Override]
			public function validate(Value $value, string ...$args): bool
			{
				if (!is_array($value->value)) {
					return false;
				}

				return count($value->value) >= (int) ($args[0] ?? 0);
			}
		};
	}

	private static function maxItems(): Validator
	{
		return new class implements Validator {
			public string $message = 'Has more than the maximum allowed number of %4$s items';

			#[Override]
			public function validate(Value $value, string ...$args): bool
			{
				if (!is_array($value->value)) {
					return false;
				}

				return count($value->value) <= (int) ($args[0] ?? 0);
			}
		};
	}
}
