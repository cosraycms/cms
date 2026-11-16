<?php

declare(strict_types=1);

namespace Cosray\Text;

/**
 * One text of a node: the value of a text field, or a richtext paragraph
 * or heading, also inside block and entry rows.
 *
 * @api
 */
final readonly class Text
{
	/**
	 * @param string $id identifies the text by node, field, locale, text and its occurrence among equal texts
	 * @param string $locale the content locale, or `*` for a field without locales
	 * @param string $pointer RFC 6901 JSON pointer to the value in the node's content
	 */
	public function __construct(
		public string $id,
		public string $node,
		public string $type,
		public ?string $title,
		public ?string $path,
		public string $field,
		public string $locale,
		public string $pointer,
		public string $value,
	) {}

	/**
	 * The id of a text. The text is part of the hash, so an id that no
	 * longer resolves means the text changed or is gone. Part of the export
	 * format: files and reports refer to texts by it.
	 */
	public static function id(string $node, string $field, string $locale, string $text, int $occurrence): string
	{
		return substr(hash('sha256', implode("\0", [$node, $field, $locale, $text, (string) $occurrence])), 0, 16);
	}

	/**
	 * The text as one line of the export format.
	 *
	 * @return array{id: string, node: string, type: string, title: ?string, path: ?string, field: string, locale: string, pointer: string, text: string}
	 */
	public function array(): array
	{
		return [
			'id' => $this->id,
			'node' => $this->node,
			'type' => $this->type,
			'title' => $this->title,
			'path' => $this->path,
			'field' => $this->field,
			'locale' => $this->locale,
			'pointer' => $this->pointer,
			'text' => $this->value,
		];
	}
}
