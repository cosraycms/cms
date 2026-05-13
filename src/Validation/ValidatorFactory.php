<?php

declare(strict_types=1);

namespace Celemas\Cms\Validation;

use Celemas\Cms\Field\Field;
use Celemas\Cms\Field\FieldHydrator;
use Celemas\Cms\Locales;
use Celemas\Cms\Node\Factory;
use Celemas\Sire\Shape;

class ValidatorFactory
{
	protected readonly Shape $shape;

	public function __construct(
		protected readonly object $node,
		protected readonly Locales $locales,
		private readonly FieldHydrator $hydrator = new FieldHydrator(),
	) {
		$this->shape = Shapes::create();
		$this->shape->add('uid', 'string')->rules('required', 'maxlen:64');
		$this->shape->add('parent', 'string')->rules('maxlen:64')->optional()->nullable();
		$this->shape->add('published', 'bool')->rules('required');
		$this->shape->add('locked', 'bool')->empty('missing', 'null')->default(false);
		$this->shape->add('hidden', 'bool')->empty('missing', 'null')->default(false);
	}

	public function create(): Shape
	{
		$contentShape = Shapes::create();

		foreach (Factory::fieldNamesFor($this->node) as $fieldName) {
			$this->add($contentShape, $fieldName, $this->hydrator->getField($this->node, $fieldName));
		}

		$this->shape->add('content', $contentShape)->optional()->nullable();

		return $this->shape;
	}

	protected function add(Shape $shape, string $fieldName, Field $field): void
	{
		$shape
			->add($fieldName, $field->shape())
			->label($field->getLabel())
			->optional()
			->nullable();
	}
}
