<?php

declare(strict_types=1);

namespace Cosray\Panel;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use IntlDateFormatter;
use Throwable;

/**
 * A node's state in the stages the panel shows as dots, the one place
 * that derives it for the editor head, the inspector and the lists.
 *
 * A node has three stages: its main version (the stored node, published
 * or not), the draft (changes to the main version the editorial team
 * saved) and the current user's unsaved edits. A record has two: the
 * stored state and the user's edits. Saving and failing autosaves are
 * client states; the server knows none of them.
 */
final readonly class NodeStatus
{
	private const array ICONS = [
		'main' => 'check-lg',
		'saved' => 'check-lg',
		'draft' => 'file-earmark-text',
		'mine' => 'pencil',
	];

	/**
	 * @param 'published'|'unpublished' $main
	 * @param 'none'|'present'|'conflict' $mine
	 */
	public function __construct(
		public bool $record,
		public string $main,
		public bool $draft,
		public string $mine,
		public string $edited = '',
		public ?string $saved = null,
		public ?string $savedBy = null,
		public ?string $mineSaved = null,
		public ?string $editor = null,
	) {}

	/**
	 * An autosave that started from an older state than the working one,
	 * the draft or else the main version, is a conflict.
	 *
	 * @param string $edited when the main version's content changed last
	 * @param ?string $editor who changed it
	 * @param ?array{changed: string, editorName?: ?string} $draft the working copy
	 * @param ?array{changed: string, base: string} $autosave the user's
	 */
	public static function of(
		bool $record,
		bool $published,
		string $edited,
		?string $editor,
		?array $draft,
		?array $autosave,
	): self {
		$mine = match (true) {
			$autosave === null => 'none',
			self::before($autosave['base'], $draft['changed'] ?? $edited) => 'conflict',
			default => 'present',
		};

		if ($record) {
			return new self(
				true,
				'published',
				false,
				$mine,
				$edited,
				$edited,
				$editor,
				$autosave['changed'] ?? null,
				$editor,
			);
		}

		return new self(
			record: false,
			main: $published ? 'published' : 'unpublished',
			draft: $draft !== null,
			mine: $mine,
			edited: $edited,
			saved: $draft['changed'] ?? null,
			savedBy: $draft['editorName'] ?? null,
			mineSaved: $autosave['changed'] ?? null,
			editor: $editor,
		);
	}

	/**
	 * The stages in their order, each with its state, name and text, a
	 * help line for the inspector, and a badge where the stage has one.
	 *
	 * @param Closure(string, bool): string $date formats a stored time, with the time of day or not
	 * @return list<array{key: string, state: string, name: string, text: string, help: string, badge: string}>
	 */
	public function stages(Closure $date): array
	{
		$stored = fn(): string => (
			$this->savedBy !== null && $this->savedBy !== ''
				? __('status:saved-by', ['date' => $date($this->saved ?? '', true), 'editor' => $this->savedBy])
				: __('status:saved-at', ['date' => $date($this->saved ?? '', true)])
		);
		$mine = [
			'key' => 'mine',
			'state' => $this->mine,
			'name' => __('status:mine'),
			'text' => match ($this->mine) {
				'present' => __('status:mine-present', ['time' => $date($this->mineSaved ?? '', true)]),
				'conflict' => __('status:mine-conflict'),
				default => __('status:mine-none'),
			},
			'help' => __('status:mine-help'),
			'badge' => '',
		];

		if ($this->record) {
			return [
				[
					'key' => 'saved',
					'state' => 'saved',
					'name' => __('status:saved'),
					'text' => $stored(),
					'help' => __('status:saved-help'),
					'badge' => '',
				],
				$mine,
			];
		}

		$published = $this->main === 'published';
		$applied = $date($this->edited, true);

		return [
			[
				'key' => 'main',
				'state' => $this->main,
				'name' => __('status:main'),
				'text' =>
					$this->editor !== null && $this->editor !== ''
						? __('status:main-applied-by', ['date' => $applied, 'editor' => $this->editor])
						: __('status:main-applied', ['date' => $applied]),
				'help' => __($published ? 'status:main-visible' : 'status:main-invisible'),
				'badge' => __($published ? 'status:online' : 'status:offline'),
			],
			[
				'key' => 'draft',
				'state' => $this->draft ? 'present' : 'none',
				'name' => __('status:draft'),
				'text' => $this->draft ? $stored() : __('status:draft-none'),
				'help' => __('status:draft-help'),
				'badge' => '',
			],
			$mine,
		];
	}

	/**
	 * Formats stored times for stages(), in a locale and time zone.
	 *
	 * @return Closure(string, bool): string
	 */
	public static function dates(string $locale, DateTimeZone $timezone): Closure
	{
		return static function (string $value, bool $time) use ($locale, $timezone): string {
			try {
				$date = new DateTimeImmutable($value, $timezone);
			} catch (Throwable) {
				return '';
			}

			$formatted = new IntlDateFormatter(
				$locale,
				IntlDateFormatter::MEDIUM,
				$time ? IntlDateFormatter::SHORT : IntlDateFormatter::NONE,
				$timezone,
			)->format($date->getTimestamp());

			return $formatted === false ? '' : $formatted;
		};
	}

	/** The panel icon of a stage in a state. */
	public static function icon(string $stage, string $state = ''): string
	{
		return $stage === 'main' && $state === 'unpublished' ? 'slash-circle' : self::ICONS[$stage] ?? 'pencil';
	}

	/**
	 * The stages up to the last that holds something; the empty ones before
	 * it keep their place.
	 *
	 * @template T of array{state: string}
	 * @param list<T> $stages
	 * @return list<T>
	 */
	public static function chain(array $stages): array
	{
		while ($stages !== [] && $stages[array_key_last($stages)]['state'] === 'none') {
			array_pop($stages);
		}

		return $stages;
	}

	/**
	 * The stages in one sentence, for an accessible name.
	 *
	 * @param list<array{name: string, text: string, badge?: string}> $stages
	 */
	public static function sentence(array $stages): string
	{
		return implode('; ', array_map(
			static fn(array $stage): string => (
				$stage['name']
				. (($stage['badge'] ?? '') !== '' ? ' (' . $stage['badge'] . ')' : '')
				. ' – '
				. $stage['text']
			),
			$stages,
		));
	}

	private static function before(string $base, string $working): bool
	{
		try {
			return new DateTimeImmutable($base) < new DateTimeImmutable($working);
		} catch (Throwable) {
			return false;
		}
	}
}
