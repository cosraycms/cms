<?php

declare(strict_types=1);

namespace Cosray\Value;

use Cosray\Field;

/**
 * @property-read Field\Entries $field
 */
class Entry extends Value
{
	/** @var array<string, Field\Field> */
	protected array $fields = [];

	public function __construct(
		Field\Owner $owner,
		Field\Entries $field,
		ValueContext $context,
		public readonly string $type,
	) {
		parent::__construct($owner, $field, $context);

		$this->initFields();
	}

	public function __toString(): string
	{
		return $this->render();
	}

	public function json(): array
	{
		return $this->unwrap();
	}

	public function uid(): ?string
	{
		$uid = $this->data['uid'] ?? null;

		return is_string($uid) ? $uid : null;
	}

	public function unwrap(): array
	{
		$result = [];

		foreach ($this->fields as $name => $field) {
			$result[$name] = $field->structure();
		}

		return [
			'uid' => $this->uid(),
			'type' => $this->type,
			'fields' => $result,
		];
	}

	public function isset(): bool
	{
		return count($this->fields) > 0;
	}

	public function render(mixed ...$args): string
	{
		$out = '<div class="entry">';

		foreach ($this->fields as $field) {
			$out .= $field->value()->render(...$args);
		}

		$out .= '</div>';

		return $out;
	}

	public function __get(string $name): mixed
	{
		if (isset($this->fields[$name])) {
			return $this->fields[$name]->value();
		}

		throw new \Cosray\Exception\NoSuchProperty("Entry doesn't have field '{$name}'");
	}

	protected function initFields(): void
	{
		$data = $this->data['fields'] ?? [];

		if (!is_array($data)) {
			$data = [];
		}

		$this->fields = $this->field->entryFieldsFor($this->type, $data);
	}
}
