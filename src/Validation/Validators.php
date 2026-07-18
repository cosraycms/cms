<?php

declare(strict_types=1);

namespace Cosray\Validation;

use Celema\Sire\Contract\Rule;
use Celema\Sire\Contract\ValidatesEmpty;
use Celema\Sire\Contract\Validation as ValidationContract;
use Celema\Sire\Contract\Value;
use Celema\Sire\RuleRegistry;
use Celema\Sire\Validation;
use Override;

final class Validators
{
	public static function registry(): RuleRegistry
	{
		return RuleRegistry::withDefaults()->withMany([
			'minitems' => self::minItems(),
			'maxitems' => self::maxItems(),
		]);
	}

	private static function minItems(): Rule
	{
		return new class implements Rule, ValidatesEmpty {
			public string $message {
				get => 'Has fewer than the minimum number of {arg1} items';
			}

			#[Override]
			public function validate(Value $value, string ...$args): ValidationContract
			{
				if (!is_array($value->value)) {
					return Validation::invalid();
				}

				return Validation::from(count($value->value) >= (int) ($args[0] ?? 0));
			}
		};
	}

	private static function maxItems(): Rule
	{
		return new class implements Rule {
			public string $message {
				get => 'Has more than the maximum allowed number of {arg1} items';
			}

			#[Override]
			public function validate(Value $value, string ...$args): ValidationContract
			{
				if (!is_array($value->value)) {
					return Validation::invalid();
				}

				return Validation::from(count($value->value) <= (int) ($args[0] ?? 0));
			}
		};
	}
}
