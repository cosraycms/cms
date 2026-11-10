<?php

declare(strict_types=1);

namespace Cosray\Panel;

use Closure;
use DateTimeImmutable;
use Throwable;

/**
 * A node's state in the stages the panel shows as dots, the one place
 * that derives it for the editor head, the inspector and the lists.
 *
 * A publishable node has three stages: the website (the content visitors
 * get, online or offline, or none before the first publication), the draft
 * (the editorial team's working state) and the current user's unsaved
 * edits. A record has two: the stored state and the user's edits. Saving
 * and failing autosaves are client states; the server knows none of them.
 */
final readonly class NodeStatus
{
	/**
	 * @param 'none'|'online'|'offline' $website
	 * @param 'none'|'present'|'conflict' $mine
	 */
	public function __construct(
		public bool $record,
		public string $website,
		public bool $draft,
		public string $mine,
		public ?string $released = null,
		public ?string $saved = null,
		public ?string $savedBy = null,
		public ?string $mineSaved = null,
	) {}

	/**
	 * Before its first publication a node keeps its working state in the
	 * node itself, which therefore counts as its draft. An autosave that
	 * started from an older state than the working one is a conflict.
	 *
	 * @param ?array{changed: string, editorName?: ?string} $draft the working copy
	 * @param ?array{changed: string, base: string} $autosave the user's
	 */
	public static function of(
		bool $record,
		bool $published,
		?string $released,
		string $changed,
		?string $editor,
		?array $draft,
		?array $autosave,
	): self {
		$working = $draft['changed'] ?? $changed;
		$mine = match (true) {
			$autosave === null => 'none',
			self::before($autosave['base'], $working) => 'conflict',
			default => 'present',
		};

		if ($record) {
			return new self(true, 'none', false, $mine, null, $changed, $editor, $autosave['changed'] ?? null);
		}

		$unreleased = $released === null;

		return new self(
			record: false,
			website: $unreleased ? 'none' : ($published ? 'online' : 'offline'),
			draft: $unreleased || $draft !== null,
			mine: $mine,
			released: $released,
			saved: $draft['changed'] ?? ($unreleased ? $changed : null),
			savedBy: $draft !== null ? $draft['editorName'] ?? null : ($unreleased ? $editor : null),
			mineSaved: $autosave['changed'] ?? null,
		);
	}

	/**
	 * The stages in their order, each with its state, name and text, and a
	 * help line for the inspector.
	 *
	 * @param Closure(string, bool): string $date formats a stored time, with the time of day or not
	 * @return list<array{key: string, state: string, name: string, text: string, help: string}>
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
		];

		if ($this->record) {
			return [
				[
					'key' => 'saved',
					'state' => 'saved',
					'name' => __('status:saved'),
					'text' => $stored(),
					'help' => __('status:saved-help'),
				],
				$mine,
			];
		}

		$released = $date($this->released ?? '', false);

		return [
			[
				'key' => 'website',
				'state' => $this->website,
				'name' => __('status:website'),
				'text' => match ($this->website) {
					'online' => __('status:website-online', ['date' => $released]),
					'offline' => __('status:website-offline', ['date' => $released]),
					default => __('status:website-none'),
				},
				'help' => __($this->website === 'online' ? 'status:website-help' : 'status:website-unreachable'),
			],
			[
				'key' => 'draft',
				'state' => $this->draft ? 'present' : 'none',
				'name' => __('status:draft'),
				'text' => $this->draft ? $stored() : __('status:draft-none'),
				'help' => __('status:draft-help'),
			],
			$mine,
		];
	}

	/**
	 * The stages in one sentence, for an accessible name.
	 *
	 * @param list<array{name: string, text: string}> $stages
	 */
	public static function sentence(array $stages): string
	{
		return implode('; ', array_map(
			static fn(array $stage): string => $stage['name'] . ' – ' . $stage['text'],
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
