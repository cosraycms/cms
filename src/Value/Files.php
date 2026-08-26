<?php

declare(strict_types=1);

namespace Cosray\Value;

use Cosray\Field\Capability\Translatable;
use Cosray\Field\Field;
use Cosray\Field\Owner;
use Cosray\Schema\TranslateMode;
use Iterator;

class Files extends Value implements Iterator
{
	protected int $pointer = 0;

	public function __construct(
		Owner $owner,
		Field&Translatable $field,
		ValueContext $context,
		protected int $index = 0,
	) {
		parent::__construct($owner, $field, $context);
	}

	public function __toString(): string
	{
		return 'Files: count(' . count($this->unwrap()) . ')';
	}

	public function rewind(): void
	{
		$this->pointer = 0;
	}

	public function current(): File
	{
		return $this->get($this->pointer);
	}

	public function key(): int
	{
		return $this->pointer;
	}

	public function count(): int
	{
		return count($this->files());
	}

	public function next(): void
	{
		$this->pointer++;
	}

	public function valid(): bool
	{
		return $this->fileItem($this->pointer) !== null;
	}

	public function get(int $index): File
	{
		return new File($this->owner, $this->field, $this->context, $index);
	}

	public function first(): File
	{
		return new File($this->owner, $this->field, $this->context, 0);
	}

	public function unwrap(): array
	{
		return $this->files();
	}

	public function json(): mixed
	{
		return $this->unwrap();
	}

	public function isset(): bool
	{
		return $this->fileItem(0) !== null;
	}

	protected function len(): int
	{
		return count($this->files());
	}

	protected function files(): array
	{
		$value = $this->data['value'] ?? [];

		if (
			!isset($this->data['type'])
			&& isset($this->data['files'])
			&& is_array($this->data['files'])
		) {
			if (
				$this->field->translateMode() === TranslateMode::Asymmetric
				&& !array_is_list($this->data['files'])
			) {
				return $this->effectiveFiles($this->data['files']);
			}

			return $this->data['files'];
		}

		if (!is_array($value)) {
			return [];
		}

		if ($this->field->translateMode() === TranslateMode::Asymmetric) {
			return $this->effectiveFiles($value);
		}

		$files = $value[Field::NEUTRAL_LOCALE] ?? [];

		return is_array($files) ? $files : [];
	}

	protected function fileItem(int $index): ?array
	{
		$item = $this->files()[$index] ?? null;

		return is_array($item) ? $item : null;
	}

	/** @param array<string, mixed> $map */
	protected function effectiveFiles(array $map): array
	{
		$locale = $this->locale;

		while ($locale) {
			$files = $map[$locale->id] ?? null;

			if ($this->hasFile($files)) {
				return $files;
			}

			$locale = $locale->fallback();
		}

		$files = $map[Field::NEUTRAL_LOCALE] ?? null;

		return $this->hasFile($files) ? $files : [];
	}

	protected function hasFile(mixed $files): bool
	{
		if (!is_array($files)) {
			return false;
		}

		foreach ($files as $file) {
			if (is_array($file) && ($file['uid'] ?? null)) {
				return true;
			}
		}

		return false;
	}
}
