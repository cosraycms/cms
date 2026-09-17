<?php

declare(strict_types=1);

namespace Cosray\User;

use Celema\Sire\Result;
use Cosray\Context;
use Cosray\Exception\RuntimeException;
use Cosray\Field\Capability\Translatable;
use Cosray\Field\Definitions;
use Cosray\Field\Field;
use Cosray\Field\FieldHydrator;
use Cosray\Field\Fieldsets;
use Cosray\Field\Services;
use Cosray\Node\FieldOwner;
use Cosray\User;
use Cosray\Validation\Shapes;

/**
 * The field-typed part of a user model: the properties a user class declares
 * beyond the account, stored under `content` in `users.data`.
 */
final class Fields
{
	private readonly FieldHydrator $hydrator;

	public function __construct(
		Services $services,
		private readonly Context $context,
	) {
		$this->hydrator = new FieldHydrator($services);
	}

	/** @return array<string, Field> */
	public function hydrate(User $user): array
	{
		$this->hydrator->hydrate($user, $user->content(), new FieldOwner($this->context, $user->uid));
		$fields = FieldHydrator::getFields($user, Definitions::for($user::class)->names());

		foreach ($fields as $name => $field) {
			if ($field instanceof Translatable && $field->isTranslatable()) {
				$class = $user::class;
				throw new RuntimeException("User field '{$class}::\${$name}' must not be translated.");
			}
		}

		return $fields;
	}

	/**
	 * What the panel's field views render, in the shape the node editor
	 * hands them.
	 *
	 * @return array{fields: list<array>, fieldsets: list<array>, content: array<string, array>}
	 */
	public function form(User $user): array
	{
		$fields = $this->hydrate($user);
		$stored = $user->content();
		$content = [];

		foreach ($fields as $name => $field) {
			$structure = $field->structure();
			$content[$name] = array_merge($structure, $stored[$name] ?? []);
			$content[$name]['type'] = $structure['type'];
		}

		return [
			'fields' => array_values(array_map(static fn(Field $field): array => $field->properties(), $fields)),
			'fieldsets' => Fieldsets::serialize(
				Definitions::for($user::class)->fieldsets(),
				array_keys($fields),
				$fields,
				$user::class,
			),
			'content' => $content,
		];
	}

	/** @param array<string, mixed> $content */
	public function validate(User $user, array $content): Result
	{
		$shape = Shapes::create();

		foreach ($this->hydrate($user) as $name => $field) {
			$shape
				->add($name, $field->shape())
				->label($field->getLabel() ?? $name)
				->optional()
				->nullable();
		}

		$form = Shapes::create();
		$form->add('content', $shape);

		return $form->validate(['content' => $content]);
	}
}
