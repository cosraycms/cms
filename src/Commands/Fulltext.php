<?php

declare(strict_types=1);

namespace Cosray\Commands;

use Celema\Console\Command;
use Celema\Console\Io;
use Cosray\Fulltext\Rebuild;
use Throwable;

#[Command('db:fulltext', 'Rebuilds full-text documents from live content and stored titles', group: 'Database')]
final class Fulltext
{
	public function __construct(
		private readonly Rebuild $rebuild,
	) {}

	public function __invoke(Io $io): int
	{
		$result = $this->rebuild->run(static function (string $uid, Throwable $error) use ($io): void {
			$io->error('Fulltext node %s: %s', $uid, $error->getMessage());
		});
		$io->line(
			"Fulltext: {$result['processed']} processed, {$result['indexed']} indexed, {$result['empty']} empty/removed, {$result['failed']} failed; {$result['missingTitles']} missing locale titles.",
		);
		if ($result['missingTitles'] > 0) {
			$io->line('Run db:titles before db:fulltext to refresh missing materialized titles.');
		}
		return $result['failed'] > 0 ? 1 : 0;
	}
}
