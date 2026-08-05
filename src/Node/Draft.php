<?php

declare(strict_types=1);

namespace Cosray\Node;

use Cosray\Exception\NoSuchField;

final class Draft
{
	public function __construct(
		public readonly object $node,
		private array $data,
	) {}

	public function uid(string $uid): self
	{
		$this->data['uid'] = $uid;

		return $this;
	}

	public function published(bool $published = true): self
	{
		$this->data['published'] = $published;

		return $this;
	}

	public function hidden(bool $hidden = true): self
	{
		$this->data['hidden'] = $hidden;

		return $this;
	}

	public function parent(?string $uid): self
	{
		$this->data['parent'] = $uid;

		return $this;
	}

	public function fieldMeta(string $field, string $key, mixed $value): self
	{
		if (!isset($this->data['content'][$field])) {
			throw new NoSuchField("Draft does not have a field named '{$field}'");
		}

		$this->data['content'][$field]['meta'][$key] = $value;

		return $this;
	}

	public function data(): array
	{
		return $this->data;
	}
}
