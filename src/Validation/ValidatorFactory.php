<?php

declare(strict_types=1);

namespace Cosray\Validation;

use Celemas\Sire\Shape;
use Cosray\Field\Field;
use Cosray\Field\FieldHydrator;
use Cosray\Locales;
use Cosray\Node\Factory;

class ValidatorFactory
{
	protected readonly Shape $shape;

	public function __construct(
		protected readonly object $node,
		protected readonly Locales $locales,
	) {
		$this->shape = Shapes::create();
		$this->shape->add('uid', 'string')->rules('required', 'maxlen:64');
		$this->shape
			->add('handle', 'string')
			->rules('maxlen:64', 'regex:/^(?!.*[.][.])[A-Za-z0-9](?:[A-Za-z0-9._-]{0,62}[A-Za-z0-9])?$/')
			->optional()
			->nullable();
		$this->shape->add('parent', 'string')->rules('maxlen:64')->optional()->nullable();
		$this->shape->add('published', 'bool')->rules('required');
		$this->shape->add('locked', 'bool')->empty('missing', 'null')->default(false);
		$this->shape->add('hidden', 'bool')->empty('missing', 'null')->default(false);
	}

	public function create(): Shape
	{
		$contentShape = Shapes::create();

		foreach (Factory::fieldNamesFor($this->node) as $fieldName) {
			$this->add($contentShape, $fieldName, FieldHydrator::getField($this->node, $fieldName));
		}

		$this->shape->add('content', $contentShape)->optional()->nullable();

		return $this->shape;
	}

	protected function add(Shape $shape, string $fieldName, Field $field): void
	{
		$shape
			->add($fieldName, $field->shape())
			->label($field->getLabel() ?? $fieldName)
			->optional()
			->nullable();
	}
}
