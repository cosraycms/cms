<?php

declare(strict_types=1);

namespace Cosray;

use Closure;
use Cosray\Field\FieldHydrator;
use Cosray\Node\Node;

final class Column
{
	private bool|Closure $bold = false;
	private bool|Closure $italic = false;
	private bool|Closure $badge = false;
	private bool|Closure $date = false;
	private ?string $sort = null;
	private string|Closure $color = '';

	public function __construct(
		public readonly string $title,
		public readonly string|Closure $field,
	) {}

	public static function new(
		string|Closure $title,
		string|Closure $field,
	): self {
		return new self($title, $field);
	}

	public function bold(bool|Closure $bold): self
	{
		$this->bold = $bold;

		return $this;
	}

	public function italic(bool|Closure $italic): self
	{
		$this->italic = $italic;

		return $this;
	}

	public function badge(bool|Closure $badge): self
	{
		$this->badge = $badge;

		return $this;
	}

	public function date(bool|Closure $date): self
	{
		$this->date = $date;

		return $this;
	}

	public function sort(?string $sort): self
	{
		$sort = trim((string) $sort);
		$this->sort = $sort === '' ? null : $sort;

		return $this;
	}

	public function sortKey(): ?string
	{
		return $this->sort;
	}

	public function get(Node $node): array
	{
		return [
			'value' => is_string($this->field)
				? $this->getValue($node, $this->field)
				: ($this->field)($node),
			'bold' => is_bool($this->bold) ? $this->bold : ($this->bold)($node),
			'italic' => is_bool($this->italic) ? $this->italic : ($this->italic)($node),
			'badge' => is_bool($this->badge) ? $this->badge : ($this->badge)($node),
			'date' => is_bool($this->date) ? $this->date : ($this->date)($node),
			'color' => is_string($this->color) ? $this->color : ($this->color)($node),
		];
	}

	private function getValue(Node $node, string $field): mixed
	{
		$inner = Node::unwrap($node);

		switch ($field) {
			case 'title':
				return $node->title();
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
			case 'meta.editor':
				$editorData = (array) $node->meta->get('editor_data', []);

				return (
					escape(
						$editorData['name'] ?? $node->meta->get('editor_username'),
					) ?? $node->meta->get('editor_email')
				);
			case 'meta.creator':
				$creatorData = (array) $node->meta->get('creator_data', []);

				return (
					escape(
						$creatorData['name'] ?? $node->meta->get('creator_username'),
					) ?? $node->meta->get('creator_email')
				);
			default:
				$hydrator = new FieldHydrator();
				$fieldObj = $hydrator->getField($inner, $field);
				$value = $fieldObj->value();

				return $value->isset() ? (string) $value : null;
		}
	}
}
