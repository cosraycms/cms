<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Plugin;

use Cosray\Config;
use Cosray\Contract\InspectorSection;
use Cosray\Node\Wrapper;
use Cosray\Panel\Inspector\Section;

/** A note field on pages, which says whether the page is online. */
final class TestInspectorSection implements InspectorSection
{
	public function __construct(
		private readonly Config $config,
	) {}

	public function section(Wrapper $node): ?Section
	{
		return $this->noted($node, '');
	}

	public function noted(Wrapper $node, string $note): ?Section
	{
		if ($node->meta->type->handle !== 'test-page') {
			return null;
		}

		return new Section(
			'test-plugin',
			'Test notes',
			'test-plugin:section',
			['published' => (bool) $node->meta->published, 'note' => $note],
			rtrim($this->config->panel->path, '/') . '/test-plugin/notes/' . $node->meta->uid,
		);
	}
}
