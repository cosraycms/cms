<?php

declare(strict_types=1);

namespace Cosray\View\Boiler;

use Celema\Boiler\Engine;
use Cosray\Markup;
use Cosray\Renderer as RendererInterface;
use Cosray\Value\Value;
use Override;

/** @phpstan-import-type DirsInput from \Celema\Boiler\Engine */
class Renderer implements RendererInterface
{
	/** Cosray's classes whose string conversion is safe HTML, trusted by every Boiler renderer. */
	public const array TRUSTED = [Value::class, Markup::class];

	/**
	 * @param DirsInput $dirs
	 * @param array<string, mixed> $defaults
	 * @param list<class-string> $trusted
	 */
	public function __construct(
		protected string|array $dirs,
		protected array $defaults = [],
		protected array $trusted = [],
		protected bool $autoescape = true,
		protected string $contentType = 'text/html',
	) {}

	/**
	 * @param non-empty-string $id
	 * @param array<string, mixed> $context
	 */
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
		$trusted = [...self::TRUSTED, ...$this->trusted];

		return $this->autoescape
			? Engine::create($dirs, $this->defaults, $trusted)
			: Engine::unescaped($dirs, $this->defaults, $trusted);
	}

	#[Override]
	public function contentType(): string
	{
		return $this->contentType;
	}
}
