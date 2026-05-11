<?php

declare(strict_types=1);

namespace Celemas\Cms\Boiler\Error;

use Celemas\Error\Renderer as RendererInterface;

/**
 * @psalm-import-type DirsInput from \Celemas\Boiler\Engine
 */
final class RendererFactory
{
	/**
	 * @param DirsInput $dirs
	 * @param array<string, mixed> $context
	 * @param list<class-string> $trusted
	 */
	public function __construct(
		private string|array $dirs,
		private array $context = [],
		private array $trusted = [],
		private bool $autoescape = true,
	) {}

	/** @param non-empty-string $template */
	public function withTemplate(string $template): RendererInterface
	{
		return new Renderer(
			$template,
			$this->dirs,
			$this->context,
			$this->trusted,
			$this->autoescape,
		);
	}
}
