<?php

declare(strict_types=1);

namespace Cosray\Boiler;

use Celemas\Boiler\Engine;
use Cosray\Renderer as RendererInterface;
use Override;

/**
 * @psalm-api
 *
 * @psalm-import-type DirsInput from \Celemas\Boiler\Engine
 */
class Renderer implements RendererInterface
{
	/**
	 * @param DirsInput $dirs
	 * @param list<class-string> $trusted
	 */
	public function __construct(
		protected string|array $dirs,
		protected array $defaults = [],
		protected array $trusted = [],
		protected bool $autoescape = true,
		protected string $contentType = 'text/html',
	) {}

	/** @param non-empty-string $id */
	#[Override]
	public function render(string $id, array $context): string
	{
		$dirs = $this->dirs;

		if (count((array) $dirs) === 0) {
			throw new RendererException('Provide at least one template directory');
		}

		$engine = $this->createEngine($dirs);

		return $engine->render($id, $context);
	}

	/** @param DirsInput $dirs */
	protected function createEngine(string|array $dirs): Engine
	{
		return $this->autoescape
			? Engine::create($dirs, $this->defaults, $this->trusted)
			: Engine::unescaped($dirs, $this->defaults, $this->trusted);
	}

	#[Override]
	public function contentType(): string
	{
		return $this->contentType;
	}
}
