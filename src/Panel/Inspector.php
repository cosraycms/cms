<?php

declare(strict_types=1);

namespace Cosray\Panel;

use Cosray\Context;
use Cosray\Contract\InspectorSection;
use Cosray\Exception\RuntimeException;
use Cosray\Node\Wrapper;
use Cosray\Panel\Inspector\Section;
use ReflectionClass;

/** The sections sites and plugins add to the inspector of the node editor. */
final class Inspector
{
	/** @var list<class-string<InspectorSection>|InspectorSection> */
	private array $sections = [];

	/** @param class-string<InspectorSection>|InspectorSection $section */
	public function add(string|InspectorSection $section): self
	{
		if (is_string($section)) {
			if (!class_exists($section) || !is_a($section, InspectorSection::class, true)) {
				throw new RuntimeException(
					'Inspector sections must implement ' . InspectorSection::class . ": {$section}",
				);
			}

			if (!new ReflectionClass($section)->isInstantiable()) {
				throw new RuntimeException("Inspector section {$section} must be instantiable");
			}
		}

		$this->sections[] = $section;

		return $this;
	}

	public function isEmpty(): bool
	{
		return $this->sections === [];
	}

	/**
	 * The sections of a node, in the order they were registered. A class
	 * name is built for the request, as dashboard cards are.
	 *
	 * @return list<Section>
	 */
	public function of(Wrapper $node, Context $context): array
	{
		$sections = [];

		foreach ($this->sections as $definition) {
			$provider = is_string($definition) ? $context->create($definition) : $definition;
			$section = $provider->section($node);

			if ($section === null) {
				continue;
			}

			if (isset($sections[$section->key])) {
				throw new RuntimeException("Two inspector sections share the key {$section->key}");
			}

			$sections[$section->key] = $section;
		}

		return array_values($sections);
	}
}
