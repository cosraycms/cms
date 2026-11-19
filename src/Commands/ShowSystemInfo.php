<?php

declare(strict_types=1);

namespace Cosray\Commands;

use Celema\Console\Command;
use Celema\Console\Io;
use Celema\Console\Opt;
use Celema\Console\Table;
use Cosray\SystemInfo;
use Cosray\SystemInfo\Text;

#[Command(
	'system:info',
	'Reports versions, extensions, limits, the database and writable directories; fails on a problem',
	group: 'System',
)]
final class ShowSystemInfo
{
	private const array MARKS = [
		'ok' => '<green>ok</green>',
		'warn' => '<yellow>warning</yellow>',
		'fail' => '<red>problem</red>',
		'info' => '',
	];

	public function __construct(
		private readonly SystemInfo $info,
	) {}

	public function __invoke(
		Io $io,
		#[Opt('Prints the report as JSON')]
		bool $json = false,
	): int {
		$report = $this->info->report();
		$code = $report['status'] === 'fail' ? 1 : 0;

		if ($json) {
			$io->write(
				'%s',
				json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL,
			);

			return $code;
		}

		$text = new Text();
		$table = new Table();
		$hints = [];

		foreach ($report['groups'] as $index => $group) {
			if ($index > 0) {
				$table->row(['', '', '']);
			}

			$table->row(['<strong>' . $io->escape($text->group($group['key'])) . '</strong>', '', '']);

			foreach ($group['entries'] as $entry) {
				$table->row([
					'  ' . $io->escape($text->label($entry['key'])),
					$io->escape($text->value($entry)),
					self::MARKS[$entry['status']],
				]);
				$hint = $text->hint($entry);

				if ($hint !== null) {
					$hints[] = [$entry['status'], $text->label($entry['key']) . ': ' . $hint];
				}
			}
		}

		$io->write($table->render());
		$io->line();
		$io->line(
			"<dim>PHP values are this console's. The web server's PHP may use other settings: see the panel's system info.</dim>",
		);

		foreach ($hints as [$status, $hint]) {
			$status === 'fail' ? $io->error('%s', $hint) : $io->warn('%s', $hint);
		}

		return $code;
	}
}
