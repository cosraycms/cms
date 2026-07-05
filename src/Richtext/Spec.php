<?php

declare(strict_types=1);

namespace Cosray\Richtext;

/**
 * The richtext format vocabulary (v1) — the single source of truth
 * for node and mark names, attribute defaults, and content models.
 * See docs/richtext-format.md for the full specification.
 */
final class Spec
{
	public const array ALIGNMENTS = ['left', 'center', 'right', 'justify'];

	/**
	 * Attribute defaults per node type. Attributes equal to their
	 * default are omitted in canonical form. Defaults are frozen per
	 * format version — changing one is a breaking change.
	 */
	public const array NODE_DEFAULTS = [
		'paragraph' => ['class' => 'default', 'align' => null],
		'heading' => ['align' => null],
		'orderedList' => ['start' => 1],
		'horizontalRule' => ['class' => null],
		'image' => ['meta' => null],
	];

	/** Mark attribute defaults, same omission rule as node defaults. */
	public const array MARK_DEFAULTS = [
		'link' => ['target' => null, 'class' => null],
	];

	/** Content models of the non-leaf nodes. */
	public const array CONTENT = [
		'doc' => 'block+',
		'paragraph' => 'inline*',
		'heading' => 'inline*',
		'bulletList' => 'listItem+',
		'orderedList' => 'listItem+',
		'listItem' => 'paragraph block*',
		'blockquote' => 'block+',
		'codeBlock' => 'text*',
	];

	public const array BLOCKS = [
		'paragraph',
		'heading',
		'bulletList',
		'orderedList',
		'blockquote',
		'codeBlock',
		'horizontalRule',
	];

	public const array INLINE = ['text', 'hardBreak', 'image'];

	public const array MARKS = [
		'bold',
		'code',
		'italic',
		'link',
		'strike',
		'style',
		'subscript',
		'superscript',
		'underline',
	];

	/** Outermost-to-innermost nesting order when rendering marks. */
	public const array MARK_ORDER = [
		'link',
		'style',
		'bold',
		'italic',
		'underline',
		'strike',
		'subscript',
		'superscript',
		'code',
	];

	public static function isNode(string $type): bool
	{
		return (
			$type === 'doc'
			|| $type === 'listItem'
			|| in_array($type, self::BLOCKS, true)
			|| in_array($type, self::INLINE, true)
		);
	}

	public static function isBlock(string $type): bool
	{
		return in_array($type, self::BLOCKS, true);
	}

	public static function isInline(string $type): bool
	{
		return in_array($type, self::INLINE, true);
	}

	public static function isLeaf(string $type): bool
	{
		return !isset(self::CONTENT[$type]);
	}

	public static function isMark(string $type): bool
	{
		return in_array($type, self::MARKS, true);
	}

	/** @return array<string, mixed> */
	public static function nodeDefaults(string $type): array
	{
		return self::NODE_DEFAULTS[$type] ?? [];
	}

	/** @return array<string, mixed> */
	public static function markDefaults(string $type): array
	{
		return self::MARK_DEFAULTS[$type] ?? [];
	}
}
