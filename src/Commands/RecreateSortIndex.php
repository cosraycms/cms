<?php

declare(strict_types=1);

namespace Cosray\Commands;

use Celema\Console\Args;
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

	public function __invoke(Args $args, Io $io): int
	{
		$result = new Indexes($this->context->db, $this->context->locales())->reconcile();
		$io->echoln(
			"Title sort indexes reconciled: {$result['created']} created, {$result['dropped']} dropped; "
				. implode(', ', $result['locales']),
		);

		return 0;
	}
}
