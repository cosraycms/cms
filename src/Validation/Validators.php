<?php

declare(strict_types=1);

namespace Cosray\Validation;

use Celema\Sire\Contract\NamedArguments;
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
	 * Translations of the messages Sire produces itself, by message key.
	 * Cosray's own rules translate their messages.
	 *
	 * @return array<string, string>
	 */
	public static function messages(): array
	{
		$messages = [
			'extra' => __('validation:extra'),
			'missing' => __('validation:missing'),
			'null' => __('validation:null'),
			'rule.email' => __('validation:email'),
			'rule.in' => __('validation:in'),
			'rule.max' => __('validation:max'),
			'rule.maxlen' => __('validation:maxlen'),
			'rule.min' => __('validation:min'),
			'rule.minlen' => __('validation:minlen'),
			'rule.regex' => __('validation:regex'),
			'rule.required' => __('validation:required'),
			'type.bool' => __('validation:type-bool'),
			'type.float' => __('validation:type-float'),
			'type.int' => __('validation:type-int'),
			'type.list' => __('validation:type-list'),
			'type.number' => __('validation:type-number'),
			'type.shape' => __('validation:type-shape'),
			'type.string' => __('validation:type-string'),
		];

		// Without a translation, __() returns the id; Sire's English reads better.
		return array_filter($messages, static fn(string $message): bool => !str_starts_with($message, 'validation:'));
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
		return new class implements Rule, ValidatesEmpty, NamedArguments {
			#[Override]
			public string $message {
				get => __('validation:min-items');
			}

			#[Override]
			public array $arguments {
				get => ['min'];
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
		return new class implements Rule, NamedArguments {
			#[Override]
			public string $message {
				get => __('validation:max-items');
			}

			#[Override]
			public array $arguments {
				get => ['max'];
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
			#[Override]
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
			#[Override]
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
			#[Override]
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
