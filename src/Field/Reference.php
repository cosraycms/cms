<?php

declare(strict_types=1);

namespace Cosray\Field;

use Celemas\Sire\Shape;
use Cosray\Validation\Prepare;
use Cosray\Validation\Shapes;
use Cosray\Value;

/**
 * A reference to one or more other nodes, stored language-neutral as an
 * ordered list of `{uid}` items. Unbounded by default; #[Limit(max: 1)]
 * makes it single. #[Pick(...)] constrains which nodes the picker offers
 * (enforced by the search endpoint).
 */
class Reference extends Field implements Capability\Limitable
{
	use Capability\IsLimitable;

	public function control(): Control
	{
		return Control::reference();
	}

	public function value(): Value\Reference
	{
		return new Value\Reference($this->owner, $this, $this->valueContext);
	}

	public function structure(mixed $value = null): array
	{
		return $this->getFileStructure('reference', $value);
	}

	public function shape(): Shape
	{
		$shape = Shapes::create();
		$this->addType($shape);

		$itemShape = Shapes::list();
		$itemShape->add('uid', 'string')->rules('required');

		$value = $shape
			->add('value', $this->zxxShape($itemShape, $this->limitValidators()))
			->rules(...$this->validators)
			->prepare(Prepare::nullAsEmpty(...));

		if (!$this->isRequired()) {
			$value->optional()->nullable();
		}

		$this->addMeta($shape);

		return $shape;
	}
}
