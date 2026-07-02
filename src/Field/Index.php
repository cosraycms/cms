<?php

declare(strict_types=1);

namespace Cosray\Field;

use Cosray\Exception\RuntimeException;

/**
 * Registry of available field types and their string aliases.
 *
 * Canonical field type references use the FQCN; aliases are
 * legacy-import sugar consumed by content normalization.
 */
final class Index
{
	/** @var array<class-string<Field>, true> */
	private array $types = [];

	/** @var array<string, class-string<Field>> */
	private array $aliases = [];

	/** @param class-string<Field> $class */
	public function add(string $class, string ...$aliases): void
	{
		if (!is_subclass_of($class, Field::class)) {
			throw new RuntimeException('Field types must extend ' . Field::class);
		}

		$this->types[$class] = true;

		foreach ($aliases as $alias) {
			$this->aliases[$alias] = $class;
		}
	}

	/** @return class-string<Field>|null */
	public function resolve(string $type): ?string
	{
		if (isset($this->aliases[$type])) {
			return $this->aliases[$type];
		}

		if (is_subclass_of($type, Field::class)) {
			return $type;
		}

		return null;
	}

	/** @return list<class-string<Field>> */
	public function all(): array
	{
		return array_keys($this->types);
	}

	public static function withDefaults(): self
	{
		$index = new self();
		$index->add(Blocks::class, 'blocks', 'grid');
		$index->add(Checkbox::class, 'checkbox');
		$index->add(Code::class, 'code');
		$index->add(Date::class, 'date');
		$index->add(DateTime::class, 'datetime');
		$index->add(Decimal::class, 'decimal');
		$index->add(Entries::class, 'entries', 'matrix');
		$index->add(File::class, 'file');
		$index->add(Iframe::class, 'iframe');
		$index->add(Image::class, 'image', 'picture');
		$index->add(Number::class, 'number');
		$index->add(Option::class, 'option');
		$index->add(Radio::class, 'radio');
		$index->add(RichText::class, 'richtext', 'html');
		$index->add(Text::class, 'text');
		$index->add(Textarea::class, 'textarea');
		$index->add(Time::class, 'time');
		$index->add(Video::class, 'video');
		$index->add(Youtube::class, 'youtube');

		return $index;
	}
}
