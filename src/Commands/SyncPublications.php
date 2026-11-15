<?php

declare(strict_types=1);

namespace Cosray\Commands;

use Celema\Console\Command;
use Celema\Console\Io;
use Celema\Console\Opt;
use Cosray\Node\Publications;

/**
 * Brings the publication rows in line with the node types after a type
 * turned into a record or out of one. Without `--apply` it only reports,
 * and exits with 1 on a mismatch, so a deploy can stop on it.
 */
#[Command(
	'db:publications',
	'Checks the publication rows against the record types; --apply syncs them',
	group: 'Database',
)]
final class SyncPublications
{
	public function __construct(
		private readonly Publications $publications,
	) {}

	public function __invoke(
		Io $io,
		#[Opt('Delete the rows of record types and create the missing ones')]
		bool $apply = false,
		#[Opt('Create the missing rows published')]
		bool $publish = false,
	): int {
		$check = $this->publications->check();

		foreach ($check['drafts'] as $node) {
			$io->line(
				'%s (%s) has a working copy its type no longer shows; apply or discard it first.',
				$node['uid'],
				$node['type'],
			);
		}

		if ($apply) {
			$result = $this->publications->sync($publish);
			$io->line(
				'Deleted %d publication rows of records, created %d missing rows%s.',
				$result['deleted'],
				$result['created'],
				$publish ? ', published' : '',
			);

			return $check['drafts'] === [] ? 0 : 1;
		}

		foreach ($check['stale'] as $node) {
			$io->line('%s (%s) is a record with a publication row.', $node['uid'], $node['type']);
		}

		foreach ($check['missing'] as $node) {
			$io->line('%s (%s) has no publication row.', $node['uid'], $node['type']);
		}

		if ($check['stale'] === [] && $check['missing'] === [] && $check['drafts'] === []) {
			$io->line('The publication rows match the node types.');

			return 0;
		}

		if ($check['stale'] !== [] || $check['missing'] !== []) {
			$io->line('Run this again with <strong>--apply</strong> to sync them.');
		}

		return 1;
	}
}
