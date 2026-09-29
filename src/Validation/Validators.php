<?php

declare(strict_types=1);

namespace Cosray\Validation;

use Celema\Sire\Contract\Rule;
use Celema\Sire\Contract\ValidatesEmpty;
use Celema\Sire\Contract\Validation as ValidationContract;
use Celema\Sire\Contract\Value;
use Celema\Sire\RuleRegistry;
use Celema\Sire\Validation;
use Cosray\DateTime\Codec;
use Cosray\Value\Color;
use Override;

final class Validators
{
	public static function registry(): RuleRegistry
	{
		return RuleRegistry::withDefaults()->withMany([
			'color' => self::color(),
			'minitems' => self::minItems(),
			'maxitems' => self::maxItems(),
			'rfc3339' => self::rfc3339(),
			'timezone' => self::timezone(),
		]);
	}

	/**
	 * An `in:` rule accepting exactly the given values, which may contain
	 * commas, colons, quotes and backslashes. Sire parses the definition
	 * twice (the rule parser splits on `:`, the rule on `,`) and each pass
	 * consumes one level of backslash escapes, so values are escaped twice.
	 *
	 * @param list<scalar> $values
	 */
	public static function in(array $values): string
	{
		$list = implode(',', array_map(
			static fn(mixed $value): string => self::escape((string) $value, ','),
			$values,
		));

		return 'in:' . self::escape($list, ':');
	}

	private static function escape(string $value, string $delimiter): string
	{
		return addcslashes($value, '\\"\'' . $delimiter);
	}

	private static function minItems(): Rule
	{
		return new class implements Rule, ValidatesEmpty {
			public string $message {
				get => __('validation:min-items');
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
				get => __('validation:max-items');
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

	private static function color(): Rule
	{
		return new class implements Rule {
			public string $message {
				get => __('validation:color');
			}

			#[Override]
			public function validate(Value $value, string ...$args): ValidationContract
			{
				return Validation::from(Color::normalize($value->value) !== null);
			}
		};
	}

	private static function rfc3339(): Rule
	{
		return new class implements Rule {
			public string $message {
				get => __('validation:rfc3339');
			}

			#[Override]
			public function validate(Value $value, string ...$args): ValidationContract
			{
				return Validation::from(is_string($value->value) && Codec::parse($value->value) !== null);
			}
		};
	}

	private static function timezone(): Rule
	{
		return new class implements Rule {
			public string $message {
				get => __('validation:timezone');
			}

			#[Override]
			public function validate(Value $value, string ...$args): ValidationContract
			{
				return Validation::from(Codec::timezone($value->value) !== null);
			}
		};
	}
}
