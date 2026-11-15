<?php

declare(strict_types=1);

namespace Cosray;

use Closure;
use Cosray\Collection\Sort;
use Cosray\Node\Wrapper;
use Cosray\User\Avatar;

/**
 * A listing column of a collection. The factories pick its kind: text,
 * number, date, date and time, user, or status.
 */
abstract class Column
{
	public protected(set) string $title;
	public protected(set) ?Sort $sort = null;

	/** @param string|Closure(Wrapper): mixed $field */
	public static function text(string $title, string|Closure $field): TextColumn
	{
		return new TextColumn($title, $field);
	}

	/** @param string|Closure(Wrapper): mixed $field */
	public static function number(string $title, string|Closure $field): NumberColumn
	{
		return new NumberColumn($title, $field);
	}

	/** @param string|Closure(Wrapper): mixed $field */
	public static function date(string $title, string|Closure $field): DateColumn
	{
		return new DateColumn($title, $field);
	}

	/** @param string|Closure(Wrapper): mixed $field */
	public static function dateTime(string $title, string|Closure $field): DateColumn
	{
		return new DateColumn($title, $field, time: true);
	}

	/** When the entry was last changed, latest first when sorted by it. */
	public static function changed(?string $title = null): DateColumn
	{
		return new DateColumn($title ?? __('collection:column-changed'), 'meta.changed', time: true)->sort(
			'changed',
			direction: 'desc',
		);
	}

	/** When the entry was created, latest first when sorted by it. */
	public static function created(?string $title = null): DateColumn
	{
		return new DateColumn($title ?? __('collection:column-created'), 'meta.created', time: true)->sort(
			'created',
			direction: 'desc',
		);
	}

	/** The user who last changed the entry. */
	public static function editor(?string $title = null): UserColumn
	{
		return new UserColumn($title ?? __('collection:column-editor'), 'editor');
	}

	/** The user who created the entry. */
	public static function creator(?string $title = null): UserColumn
	{
		return new UserColumn($title ?? __('collection:column-creator'), 'creator');
	}

	/** @param Closure(Wrapper): (User|Avatar|null) $user */
	public static function user(string $title, Closure $user): UserColumn
	{
		return new UserColumn($title, $user);
	}

	/**
	 * A column of a node's stages, all of them unless named. Placed
	 * anywhere, it replaces the one a listing adds after its first column.
	 */
	public static function status(Stage ...$stages): StatusColumn
	{
		return new StatusColumn(...$stages);
	}

	/**
	 * The listing columns of a collection without its own: the title, which
	 * is also the initial order, and the last change.
	 *
	 * @return list<self>
	 */
	public static function defaults(): array
	{
		return [
			self::text(__('collection:column-title'), 'title')->bold()->sort('title'),
			self::changed(),
		];
	}

	/** What the column holds, for the listing to size its track and format its cells by. */
	abstract public function kind(): string;

	/**
	 * The cell of a node: its value and styles.
	 *
	 * @return array<string, mixed>
	 */
	abstract public function get(Wrapper $node): array;
}
