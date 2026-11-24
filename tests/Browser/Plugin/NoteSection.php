<?php

declare(strict_types=1);

namespace Cosray\Tests\Browser\Plugin;

use Cosray\Config;
use Cosray\Contract\InspectorSection;
use Cosray\Node\Wrapper;
use Cosray\Panel\Inspector\Section;
use Override;

final class NoteSection implements InspectorSection
{
	public function __construct(
		private readonly Config $config,
	) {}

	#[Override]
	public function section(Wrapper $node): ?Section
	{
		return $this->noted($node, '');
	}

	public function noted(Wrapper $node, string $note): ?Section
	{
		// Only this node has it, so the other tests' editors stay as they are.
		if ($node->meta->uid !== 'browsersection') {
			return null;
		}

		return new Section(
			'browser-notes',
			'Notes',
			'browser-notes:section',
			['online' => (bool) $node->meta->published, 'note' => $note],
			rtrim($this->config->panel->path, '/') . '/browser-notes/' . $node->meta->uid,
		);
	}
}
