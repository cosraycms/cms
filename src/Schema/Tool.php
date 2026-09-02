<?php

declare(strict_types=1);

namespace Cosray\Schema;

/**
 * The richtext toolbar vocabulary. `#[Tools]` and the `richtext.tools`
 * config key pick from these cases; the panel renders whatever is picked
 * in its own canonical order, so the list is a set, not a layout.
 */
enum Tool: string
{
	case Undo = 'undo';
	case Redo = 'redo';
	case H1 = 'h1';
	case H2 = 'h2';
	case H3 = 'h3';
	case Bold = 'bold';
	case Italic = 'italic';
	case Strike = 'strike';
	case Sub = 'sub';
	case Sup = 'sup';
	case Align = 'align';
	case BulletList = 'bullet-list';
	case OrderedList = 'ordered-list';
	case Blockquote = 'blockquote';
	case Hr = 'hr';
	case Link = 'link';
	case Image = 'image';
	case Br = 'br';
	case Clear = 'clear';
	case Source = 'source';

	/**
	 * The built-in toolbar: what editors get unless a field's `#[Tools]`
	 * or the project's `richtext.tools` asks for more.
	 *
	 * @return list<Tool>
	 */
	public static function defaults(): array
	{
		return [
			self::Undo,
			self::Redo,
			self::Bold,
			self::Italic,
			self::Strike,
			self::H2,
			self::H3,
			self::BulletList,
			self::OrderedList,
			self::Link,
		];
	}
}
