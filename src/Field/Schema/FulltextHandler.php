<?php

declare(strict_types=1);

namespace Cosray\Field\Schema;

use Cosray\Exception\RuntimeException;
use Cosray\Field;
use Cosray\Field\Capability\Searchable;

class FulltextHandler extends Handler
{
	public function apply(object $meta, Field\Field $field): void
	{
		if ($field instanceof Searchable && self::supports($field::class)) {
			$field->fulltext($meta->fulltextWeight);

			return;
		}

		if ($meta->fulltextWeight === false) {
			return;
		}

		throw new RuntimeException("Fulltext does not support field '{$field->name}' of type '{$field->type}'.");
	}

	public static function supports(string $type): bool
	{
		// Text subclasses include code and embed markup, not just prose.
		return in_array(
			$type,
			[
				Field\Text::class,
				Field\Textarea::class,
				Field\RichText::class,
				Field\Blocks::class,
				Field\Entries::class,
			],
			true,
		);
	}

	public function properties(object $meta, Field\Field $field): array
	{
		return [];
	}
}
