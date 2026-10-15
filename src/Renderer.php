<?php

declare(strict_types=1);

namespace Cosray;

interface Renderer
{
	/**
	 * @param non-empty-string $id
	 * @param array<string, mixed> $context
	 */
	public function render(string $id, array $context): string;

	public function contentType(): string;
}
