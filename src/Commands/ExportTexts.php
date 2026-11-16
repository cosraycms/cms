<?php

declare(strict_types=1);

namespace Cosray\Commands;

use Celema\Console\Command;
use Celema\Console\Io;
use Celema\Console\Opt;
use Cosray\Cms;
use Cosray\Text\Texts;

#[Command('texts:export', 'Writes the texts of published nodes as JSON lines', group: 'Content')]
final class ExportTexts
{
	public function __construct(
		private readonly Cms $cms,
		private readonly Texts $texts,
	) {}

	/**
	 * @param list<string> $type
	 * @param list<string> $exclude
	 */
	public function __invoke(
		Io $io,
		#[Opt('The file to write, standard output by default')]
		string $out = '',
		#[Opt('Only nodes of this type handle; repeatable')]
		array $type = [],
		#[Opt('Leave out nodes of this type handle; repeatable')]
		array $exclude = [],
	): int {
		$handle = $out === '' ? STDOUT : fopen($out, 'wb');

		if ($handle === false) {
			$io->error('Cannot write %s.', $out);

			return 1;
		}

		$nodes = $this->cms->nodes->published(true)->hidden(null)->order('type', 'id');

		if ($type !== []) {
			$nodes->types(...$type);
		}

		$count = 0;
		$seen = [];

		foreach ($this->texts->of($nodes) as $text) {
			if (in_array($text->type, $exclude, true)) {
				continue;
			}

			fwrite(
				$handle,
				json_encode($text->array(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
					. "\n",
			);
			$seen[$text->node] = true;
			$count++;
		}

		if ($out !== '') {
			fclose($handle);
			$io->success('Wrote %d texts of %d nodes to %s.', $count, count($seen), $out);
		}

		return 0;
	}
}
