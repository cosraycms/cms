<?php

declare(strict_types=1);

namespace Cosray\Field;

use Cosray\Block\Registry as Blocks;
use Cosray\Field\Control\Registry as Controls;
use Cosray\Field\Schema\Registry;
use Cosray\Node\Types;

/**
 * Shared services a field needs during initialization.
 *
 * Constructed once in the CMS plugin and container-bound so all
 * hydration paths use the same registries.
 */
final class Services
{
	public readonly Blocks $blocks;
	public readonly Controls $controls;

	public function __construct(
		public readonly Registry $schemas,
		public readonly Types $types,
		?Blocks $blocks = null,
		?Controls $controls = null,
	) {
		$this->blocks = $blocks ?? Blocks::withDefaults();
		$this->controls = $controls ?? Controls::withDefaults();
	}

	public static function withDefaults(): self
	{
		return new self(Registry::withDefaults(), new Types());
	}
}
