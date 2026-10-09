<?php

declare(strict_types=1);

namespace Cosray\Commands;

use Celema\Console\Command;
use Celema\Console\Io;
use Cosray\Context;
use Cosray\Title\Indexes;

#[Command(
	'db:recreate-sort-index',
	'Reconciles title sort indexes with configured locales and their fallbacks',
	group: 'Database',
)]
final class RecreateSortIndex
{
	public function __construct(
		private readonly Context $context,
	) {}

	public function __invoke(Io $io): int
	{
		$result = new Indexes($this->context->db, $this->context->locales())->reconcile();
		$io->line(
			'Title sort indexes reconciled: %d created, %d dropped; %s',
			$result['created'],
			$result['dropped'],
			implode(', ', $result['locales']),
		);

		return 0;
	}
}
