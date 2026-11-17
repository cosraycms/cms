<?php

declare(strict_types=1);

namespace Cosray;

use Closure;
use Cosray\Collection\Sort;
use Cosray\Exception\RuntimeException;
use Cosray\Finder\SortField;
use Cosray\Node\Factory;
use Cosray\Node\Wrapper;
use Cosray\Value\Value;
use Override;

/**
 * A listing column of a field, a node's meta value like `meta.created`, or
 * a closure's result, made by Column::text().
 */
class TextColumn extends Column
{
	public private(set) bool $bold = false;
	public private(set) bool $italic = false;

	/** @var ?list<string> */
	private ?array $search = null;

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

	/**
	 * Makes the panel search match the column: what it shows, or the
	 * finder fields named, such as `uid`, `title`, `type` or a content
	 * field. `false` leaves out a column the search matches by default.
	 */
	public function search(string|false ...$fields): static
	{
		if ($fields === [false]) {
			$this->search = [];

			return $this;
		}

		$named = [];

		foreach ($fields as $field) {
			if (!is_string($field) || trim($field) === '') {
				throw new RuntimeException("Column '{$this->title}': search() takes field names, or false alone");
			}

			$named[] = trim($field);
		}

		$this->search = $named === []
			? [
				$this->shown() ?? throw new RuntimeException(
					"Column '{$this->title}' shows no field the search can match: name the fields to search",
				),
			]
			: $named;

		return $this;
	}

	#[Override]
	public function searched(): array
	{
		return $this->search ?? $this->searchedByDefault();
	}

	/**
	 * What the search matches without search(): a title column's title.
	 *
	 * @return list<string>
	 */
	protected function searchedByDefault(): array
	{
		return $this->field === 'title' ? ['title'] : [];
	}

	/** The finder field the column shows, null for a closure or a meta value without one. */
	private function shown(): ?string
	{
		return match (true) {
			!is_string($this->field) => null,
			$this->field === 'meta.name' => 'type',
			$this->field === 'meta.uid' => 'uid',
			$this->field === 'meta.handle' => 'handle',
			str_starts_with($this->field, 'meta.') => null,
			default => $this->field,
		};
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
			'value' => is_string($this->field) ? $this->value($node, $this->field) : ($this->field)($node),
			'bold' => $this->bold,
			'italic' => $this->italic,
		];
	}

	/** The cell value of a content field. */
	protected function fieldValue(Value $value): mixed
	{
		return $value->isset() ? (string) $value : null;
	}

	private function value(Wrapper $node, string $field): mixed
	{
		$inner = Wrapper::unwrap($node);

		switch ($field) {
			case 'title':
				return $node->label();
			case 'meta.name':
				// A type's label is a message id, translated like everywhere else in the panel.
				return __((string) $node->meta->name);
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
				return $this->fieldValue(Factory::fieldFor($inner, $field)->value());
		}
	}
}
