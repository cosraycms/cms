<?php

declare(strict_types=1);

namespace Cosray;

use Closure;
use Cosray\Collection\Sort;
use Cosray\Finder\SortField;
use Cosray\Node\Factory;
use Cosray\Node\Wrapper;
use Override;

/**
 * A listing column of a field, a node's meta value like `meta.created`, or
 * a closure's result, made by Column::text().
 */
class TextColumn extends Column
{
	public private(set) bool $bold = false;
	public private(set) bool $italic = false;

	/** @param string|Closure(Wrapper): mixed $field */
	public function __construct(
		string $title,
		public readonly string|Closure $field,
	) {
		$this->title = $title;
	}

	public function bold(bool $bold = true): static
	{
		$this->bold = $bold;

		return $this;
	}

	public function italic(bool $italic = true): static
	{
		$this->italic = $italic;

		return $this;
	}

	/**
	 * @param non-empty-list<string|SortField>|null $fields Without them, the
	 *        key is the order field, ordered as the column's kind
	 * @param bool $default Whether the listing starts in this order; without
	 *                      a flagged column it starts in the first sortable one
	 */
	public function sort(
		?string $key,
		?array $fields = null,
		string $direction = 'asc',
		bool $default = false,
	): static {
		$this->sort = $key === null
			? null
			: new Sort(trim($key), $fields ?? [$this->sortField(trim($key))], $direction, $default);

		return $this;
	}

	/** How the column orders by a key it was given without fields. */
	protected function sortField(string $key): string|SortField
	{
		return $key;
	}

	/** Whether the column shows a meta value, which orders by its native type. */
	protected function meta(): bool
	{
		return is_string($this->field) && str_starts_with($this->field, 'meta.');
	}

	#[Override]
	public function kind(): string
	{
		return 'text';
	}

	/** @return array{value: mixed, bold: bool, italic: bool} */
	#[Override]
	public function get(Wrapper $node): array
	{
		return [
			'value' => is_string($this->field) ? self::value($node, $this->field) : ($this->field)($node),
			'bold' => $this->bold,
			'italic' => $this->italic,
		];
	}

	private static function value(Wrapper $node, string $field): mixed
	{
		$inner = Wrapper::unwrap($node);

		switch ($field) {
			case 'title':
				return $node->label();
			case 'meta.name':
				return $node->meta->name;
			case 'meta.uid':
			case 'meta.published':
			case 'meta.hidden':
			case 'meta.locked':
			case 'meta.created':
			case 'meta.changed':
			case 'meta.deleted':
			case 'meta.content':
			case 'meta.handle':
				$key = explode('.', $field)[1];

				return $node->meta->get($key);
			case 'meta.class':
				return $inner::class;
			case 'meta.classname':
				return basename(str_replace('\\', '/', $inner::class));
			default:
				$fieldObj = Factory::fieldFor($inner, $field);
				$value = $fieldObj->value();

				return $value->isset() ? (string) $value : null;
		}
	}
}
