<?php

declare(strict_types=1);

namespace Cosray\Value;

use Cosray\Assets\Asset;
use Cosray\Exception\RuntimeException;
use Cosray\Field\Capability\Translatable;
use Cosray\Field\Field;
use Cosray\Field\Owner;
use Cosray\Schema\TranslateMode;

/**
 * @property-read Field&Translatable $field
 */
class File extends Value
{
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
		return $this->publicPath(false);
	}

	public function title(): string
	{
		return $this->textValue('title', $this->index);
	}

	public function url(bool $bust = false): string
	{
		unset($bust);
		$url = $this->owner->request()->origin() . $this->mediaPath($this->index);

		if ($url = filter_var($url, FILTER_VALIDATE_URL)) {
			return $url;
		}

		throw new RuntimeException('Invalid file url');
	}

	public function publicPath(bool $bust = false): string
	{
		unset($bust);

		return filter_var($this->mediaPath($this->index), FILTER_SANITIZE_URL);
	}

	public function filename(): string
	{
		return $this->getFileName($this->index);
	}

	public function mimetype(): string
	{
		return $this->asset($this->index)->mime ?? '';
	}

	public function unwrap(): ?array
	{
		return $this->fileItem(0);
	}

	public function json(): mixed
	{
		return $this->data;
	}

	public function count(): int
	{
		return count($this->files());
	}

	public function isset(): bool
	{
		return $this->fileItem(0) !== null;
	}

	/** The serving route type this value links to. */
	protected function mediaType(): string
	{
		return 'file';
	}

	protected function mediaPath(int $index): string
	{
		return $this->asset($index)?->mediaPath($this->mediaType()) ?? '';
	}

	protected function asset(int $index): ?Asset
	{
		$uid = (string) ($this->fileItem($index)['uid'] ?? '');

		return $uid === '' ? null : $this->owner->assets()->get($uid);
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

	protected function getFileName(int $index): string
	{
		return $this->asset($index)->filename ?? '';
	}

	/**
	 * Per-use meta wins only when it is non-empty for the effective
	 * locale; otherwise the asset's catalog default applies.
	 */
	protected function textValue(string $key, int $index): string
	{
		$item = $this->fileItem($index) ?? [];
		$value = $item['meta'][$key] ?? null;

		if (!is_array($value) && !isset($this->data['type']) && array_key_exists($key, $item)) {
			$value = $item[$key];
		}

		$result = $this->textFrom($value);

		if ($result !== '') {
			return $result;
		}

		return $this->textFrom($this->asset($index)?->metaMap($key));
	}

	protected function textFrom(mixed $value): string
	{
		if (!is_array($value)) {
			return is_string($value) || is_numeric($value) ? (string) $value : '';
		}

		$value = $this->effective($value);

		if (is_string($value) || is_numeric($value)) {
			return (string) $value;
		}

		return '';
	}
}
