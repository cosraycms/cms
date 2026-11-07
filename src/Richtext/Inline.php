<?php

declare(strict_types=1);

namespace Cosray\Richtext;

use Cosray\Assets\Asset;
use Cosray\References\Resolver;
use Override;

/**
 * The small richtext of a caption: a document with one paragraph of text in
 * bold, italic or struck through. A plain string stays valid and reads as plain
 * text, so captions written before this keep working until they are next saved.
 */
final class Inline
{
	public const array MARKS = ['bold', 'italic', 'strike'];

	/**
	 * A document from what a writer hands in: a document, its JSON, or plain
	 * text. Null when that is empty or not this vocabulary.
	 *
	 * @return ?array<string, mixed>
	 */
	public static function read(mixed $value): ?array
	{
		if (is_string($value)) {
			$decoded = json_decode($value, true);

			if (!is_array($decoded) || ($decoded['type'] ?? null) !== 'doc') {
				return self::fromText($value);
			}

			$value = $decoded;
		}

		return self::valid($value) && self::text($value) !== '' ? $value : null;
	}

	/** Whether a stored value is empty, plain text, or a document of this vocabulary. */
	public static function valid(mixed $value): bool
	{
		if ($value === null || is_string($value)) {
			return true;
		}

		if (!is_array($value) || new Validator()->validate($value) !== []) {
			return false;
		}

		$blocks = $value['content'] ?? [];

		if (count($blocks) !== 1 || ($blocks[0]['type'] ?? null) !== 'paragraph' || isset($blocks[0]['attrs'])) {
			return false;
		}

		foreach ($blocks[0]['content'] ?? [] as $node) {
			if (($node['type'] ?? null) !== 'text') {
				return false;
			}

			foreach ($node['marks'] ?? [] as $mark) {
				if (!in_array($mark['type'] ?? null, self::MARKS, true)) {
					return false;
				}
			}
		}

		return true;
	}

	/** Printable HTML, escaped by construction. */
	public static function html(mixed $value): string
	{
		if (!is_array($value)) {
			return is_string($value) ? \Cosray\escape($value) : '';
		}

		return new Renderer(self::noReferences())->inline($value);
	}

	public static function text(mixed $value): string
	{
		if (is_string($value)) {
			return trim($value);
		}

		$text = '';

		foreach (is_array($value) ? $value['content'] ?? [] : [] as $block) {
			foreach (is_array($block) ? $block['content'] ?? [] : [] as $node) {
				$text .= is_array($node) && is_string($node['text'] ?? null) ? $node['text'] : '';
			}
		}

		return trim($text);
	}

	/** @return ?array<string, mixed> */
	private static function fromText(string $text): ?array
	{
		$text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);

		if ($text === '') {
			return null;
		}

		return [
			'type' => 'doc',
			'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]]],
		];
	}

	/** This vocabulary refers to nothing, and a reader skips what it does not know. */
	private static function noReferences(): Resolver
	{
		return new class implements Resolver {
			#[Override]
			public function asset(string $uid): ?Asset
			{
				return null;
			}

			#[Override]
			public function nodePath(string $uid): ?string
			{
				return null;
			}

			#[Override]
			public function localize(array $map): mixed
			{
				return null;
			}
		};
	}
}
