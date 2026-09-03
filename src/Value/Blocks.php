<?php

declare(strict_types=1);

namespace Cosray\Value;

use Cosray\Block\RenderContext;
use Cosray\Block\Types;
use Cosray\Field;
use Cosray\Field\Owner;
use Cosray\Schema\Responsive;
use Cosray\Schema\TranslateMode;
use Generator;
use IteratorAggregate;

use function Cosray\escape;

/**
 * @property-read Field\Blocks $field
 */
class Blocks extends Value implements IteratorAggregate
{
	/** @var list<Block> */
	protected array $blocks = [];

	public function __construct(Owner $owner, Field\Blocks $field, ValueContext $context)
	{
		parent::__construct($owner, $field, $context);

		$this->blocks = $this->rows($this->list());
	}

	public function __toString(): string
	{
		return $this->render();
	}

	public function json(): array
	{
		return [
			'columns' => $this->columns(),
			'blocks' => array_map(static fn(Block $block): array => $block->json(), $this->blocks),
		];
	}

	public function unwrap(): array
	{
		return [
			'columns' => $this->columns(),
			'blocks' => array_map(static fn(Block $block): array => $block->unwrap(), $this->blocks),
		];
	}

	public function getIterator(): Generator
	{
		foreach ($this->blocks as $block) {
			yield $block;
		}
	}

	public function count(): int
	{
		return count($this->blocks);
	}

	public function first(): ?Block
	{
		return $this->blocks[0] ?? null;
	}

	public function last(): ?Block
	{
		return $this->blocks[count($this->blocks) - 1] ?? null;
	}

	public function get(int $index): ?Block
	{
		return $this->blocks[$index] ?? null;
	}

	public function isset(): bool
	{
		return count($this->blocks) > 0;
	}

	public function columns(): int
	{
		return $this->field->getColumns();
	}

	public function responsive(): Responsive
	{
		return $this->field->getResponsive();
	}

	/** The n-th image block's image. */
	public function image(int $index = 1): ?Image
	{
		$i = 0;

		foreach ($this->blocks as $block) {
			if ($block->type !== Types\Image::class) {
				continue;
			}

			if (++$i === $index) {
				$image = $block->image;

				return $image instanceof Image ? $image : null;
			}
		}

		return null;
	}

	/**
	 * Every image of the image and images blocks. With `$all` a field
	 * translated per locale yields the images of every locale's list.
	 */
	public function images(bool $all = false): Generator
	{
		$lists = $all && $this->perLocale() ? $this->lists() : [$this->blocks];

		foreach ($lists as $blocks) {
			foreach ($blocks as $block) {
				if ($block->type === Types\Image::class) {
					$image = $block->image;

					if ($image instanceof Image) {
						yield $image;
					}
				} elseif ($block->type === Types\Images::class) {
					foreach ($block->images as $image) {
						yield $image;
					}
				}
			}
		}
	}

	public function hasImage(int $index = 1): bool
	{
		return $this->image($index) !== null;
	}

	/** The n-th richtext block's excerpt. */
	public function excerpt(
		int $words = 30,
		string $allowedTags = '',
		int $index = 1,
	): string {
		$i = 0;

		foreach ($this->blocks as $block) {
			if ($block->type !== Types\RichText::class) {
				continue;
			}

			if (++$i === $index) {
				$text = $block->text;

				return $text instanceof RichText ? $text->excerpt($words, $allowedTags) : '';
			}
		}

		return '';
	}

	// Supported args:
	//
	// - prefix: All generated css classes are prefixed with this value.
	//   Default 'cms'
	// - tag: The tag of the container. Default 'div'
	// - class: An additional class added to the container
	// - imageSizes: `media.sizes` names forming the image block's srcset
	//   ladder. Default ['block-sm', 'block', 'block-lg']
	// - sizes: Template for the image block's `sizes` attribute; `{pct}`
	//   is replaced with the block's grid share in percent.
	//   Default '(min-width: 48rem) {pct}vw, 100vw'
	// - thumbSize: `media.sizes` name for gallery thumbs. Default 'block-thumb'
	public function render(mixed ...$args): string
	{
		$columns = $this->columns();
		$ctx = new RenderContext($this->owner, $this->fieldName, $columns, $args);
		$class = $ctx->prefix() . '-blocks' . ($ctx->class() !== '' ? ' ' . $ctx->class() : '');
		$out =
			'<'
			. $ctx->tag()
			. ' class="'
			. escape($class)
			. "\" data-columns=\"{$columns}\" data-responsive=\""
			. escape($this->responsive()->value)
			. "\" style=\"--columns: {$columns}\">";
		$registry = $this->field->services()->blocks;
		$types = [];

		foreach ($this->blocks as $block) {
			$types[$block->type] ??= $registry->create($block->type, $this->owner);
			$out .= $block->renderWith($ctx, $types[$block->type]);
		}

		return $out . '</' . $ctx->tag() . '>';
	}

	private function perLocale(): bool
	{
		return $this->field->translateMode() === TranslateMode::Asymmetric;
	}

	/**
	 * The stored list for the effective locale (per-locale lists, along
	 * the fallback chain) or the shared list.
	 */
	private function list(): array
	{
		$value = $this->data['value'] ?? null;

		if (!is_array($value)) {
			return [];
		}

		$list = $this->perLocale() ? $this->effective($value) : $value[Field\Field::NEUTRAL_LOCALE] ?? [];

		return is_array($list) ? $list : [];
	}

	/** @return list<list<Block>> every stored list, one per locale */
	private function lists(): array
	{
		$value = $this->data['value'] ?? null;
		$lists = [];

		foreach (is_array($value) ? $value : [] as $list) {
			if (is_array($list)) {
				$lists[] = $this->rows($list);
			}
		}

		return $lists;
	}

	/** @return list<Block> */
	private function rows(array $list): array
	{
		$rows = [];

		foreach ($list as $row) {
			if (!is_array($row)) {
				continue;
			}

			$type = $row['type'] ?? null;

			if (!is_string($type) || !$this->field->allows($type)) {
				continue;
			}

			$rows[] = new Block($this->owner, $this->field, new ValueContext($this->fieldName, $row), $type);
		}

		return $rows;
	}
}
