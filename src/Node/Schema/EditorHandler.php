<?php

declare(strict_types=1);

namespace Cosray\Node\Schema;

use Cosray\Schema\Editor;
use Override;

class EditorHandler extends Handler
{
	#[Override]
	public function resolve(object $meta, string $nodeClass): array
	{
		assert($meta instanceof Editor, 'The registry maps #[Editor] to this handler');

		$template = trim($meta->template);

		return ['editor' => $template === '' ? null : $template];
	}
}
