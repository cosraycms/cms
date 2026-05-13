<?php

declare(strict_types=1);

namespace Celemas\Cms\Validation;

use Celemas\Sire\Contract\Rule;
use Celemas\Sire\Contract\ValidatesEmpty;
use Celemas\Sire\Contract\Validation as ValidationContract;
use Celemas\Sire\Contract\Value;
use Celemas\Sire\RuleRegistry;
use Celemas\Sire\Validation;
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
