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
 * saved) and the current user's unsaved edits. A type without drafts has
 * no draft stage, and a record, which has no publication either, has its
 * saved state in place of a main version. A type without a route is
 * active or inactive rather than online or offline. Saving and failing
 * autosaves are client states; the server knows none of them.
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
	 * @param 'published'|'unpublished'|'saved' $main
	 * @param 'none'|'present'|'conflict' $mine
	 */
	public function __construct(
		public bool $drafts,
		public bool $publication,
		public string $main,
		public bool $draft,
		public string $mine,
		public string $edited = '',
		public ?string $saved = null,
		public ?string $savedBy = null,
		public ?string $mineSaved = null,
		public ?string $editor = null,
		public bool $routable = true,
	) {}

	/**
	 * Edits that started from another revision than the working one, the
	 * draft's or else the main version's, are a conflict, and so are edits
	 * that do not know theirs.
	 *
	 * @param bool $drafts whether the type keeps a working copy
	 * @param bool $publication whether the type is published, which a record is not
	 * @param bool $routable whether the type has pages, online or not, rather than entries the website uses
	 * @param string $edited when the main version became main
	 * @param ?string $editor who made it main
	 * @param ?int $revision the main revision
	 * @param ?array{changed: string, editorName?: ?string, revision?: ?int} $draft the working copy
	 * @param ?array{changed: string, base: ?int} $autosave the user's
	 */
	public static function of(
		bool $drafts,
		bool $publication,
		bool $published,
		string $edited,
		?string $editor,
		?array $draft,
		?array $autosave,
		?int $revision = null,
		bool $routable = true,
	): self {
		$draft = $drafts ? $draft : null;
		$working = $draft['revision'] ?? $revision;
		$mine = match (true) {
			$autosave === null => 'none',
			$autosave['base'] === null || $working !== null && $autosave['base'] !== $working => 'conflict',
			default => 'present',
		};

		return new self(
			drafts: $drafts,
			publication: $publication,
			main: match (true) {
				!$publication => 'saved',
				$published => 'published',
				default => 'unpublished',
			},
			draft: $draft !== null,
			mine: $mine,
			edited: $edited,
			saved: $drafts ? $draft['changed'] ?? null : $edited,
			savedBy: $drafts ? $draft['editorName'] ?? null : $editor,
			mineSaved: $autosave['changed'] ?? null,
			editor: $editor,
			routable: $routable,
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

		if (!$this->publication) {
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
		$main = [
			'key' => 'main',
			'state' => $this->main,
			'name' => __('status:main'),
			'text' => match (true) {
				!$this->drafts => $stored(),
				$this->editor !== null && $this->editor !== '' => __(
					'status:main-applied-by',
					['date' => $applied, 'editor' => $this->editor],
				),
				default => __('status:main-applied', ['date' => $applied]),
			},
			'help' => __($this->routable ? 'status:main-help' : 'status:main-help-active'),
			'badge' => __(match (true) {
				$this->routable => $published ? 'status:online' : 'status:offline',
				default => $published ? 'status:active' : 'status:inactive',
			}),
		];

		if (!$this->drafts) {
			return [$main, $mine];
		}

		return [
			$main,
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
}
