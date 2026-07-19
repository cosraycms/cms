<?php

declare(strict_types=1);

namespace Cosray\Commands;

use Celema\Console\Args;
use Celema\Console\Command;
use Celema\Console\Io;
use Celema\Quma\Connection;
use Cosray\Cms;
use Cosray\Context;
use Cosray\Locales;
use Cosray\Node\Types;
use Cosray\Title\Rebuild;

/**
 * Re-materializes every node's title from its content. Needs the booted app
 * (node type registry + locales), so it is constructed with those services —
 * unlike the self-describing `db:references`/`db:fulltext` commands.
 */
#[Command('db:titles', 'Rebuilds the materialized node titles from content', group: 'Database')]
class Titles
{
	public function __construct(
		private readonly Connection $conn,
		private readonly Context $context,
		private readonly Cms $cms,
		private readonly Locales $locales,
		private readonly Types $types,
	) {}

	public function __invoke(Args $args, Io $io): int
	{
		$result = new Rebuild(
			$this->context,
			$this->cms,
			$this->locales,
			$this->conn,
			$this->types,
		)->run();

		echo
			"Node titles rebuilt: {$result['nodes']} node(s), "
				. "{$result['dynamic']} dynamic, {$result['empty']} without a title\n"
		;

		return 0;
	}
}
