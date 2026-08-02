<?php

declare(strict_types=1);

namespace Cosray\Field;

use Cosray\Exception\RuntimeException;

/** Serializes fieldset descriptors shared by node and entry schemas. */
final class Fieldsets
{
	/**
	 * @param list<FieldsetDefinition> $fieldsets
	 * @param list<string> $ordered
	 * @param array<string, Field> $fields
	 * @param class-string $owner
	 *
	 * @return list<array{name: string, label: ?string, description: ?string, width: int, fields: list<string>}>
	 */
	public static function serialize(
		array $fieldsets,
		array $ordered,
		array $fields,
		string $owner,
	): array {
		$result = [];

		foreach ($fieldsets as $fieldset) {
			$members = array_values(array_filter(
				$ordered,
				static fn(string $name): bool => in_array($name, $fieldset->fields, true),
			));

			if ($members === []) {
				continue;
			}

			$first = (int) array_search($members[0], $ordered, true);

			if (array_slice($ordered, $first, count($members)) !== $members) {
				throw new RuntimeException(
					"Field order for '{$owner}' splits fieldset '{$fieldset->name}'.",
				);
			}

			$visible = array_values(array_filter(
				$members,
				static fn(string $name): bool => !($fields[$name]->properties()['hidden'] ?? false),
			));

			if ($visible === []) {
				continue;
			}

			$result[] = [
				'name' => $fieldset->name,
				'label' => $fieldset->label === null ? null : __($fieldset->label),
				'description' => $fieldset->description === null ? null : __($fieldset->description),
				'width' => $fieldset->width,
				'fields' => $visible,
			];
		}

		return $result;
	}
}
