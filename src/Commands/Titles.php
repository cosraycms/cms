<?php

declare(strict_types=1);

namespace Cosray\Commands;

use Celemas\Quma\Commands\Command;
use Celemas\Quma\Connection;
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
class Titles extends Command
{
	protected string $group = 'Database';
	protected string $prefix = 'db';
	protected string $name = 'titles';
	protected string $description = 'Rebuilds the materialized node titles from content';

	public function __construct(
		Connection $conn,
		private readonly Context $context,
		private readonly Cms $cms,
		private readonly Locales $locales,
		private readonly Types $types,
	) {
		parent::__construct($conn);
	}

	public function run(): int
	{
		$result = new Rebuild(
			$this->context,
			$this->cms,
			$this->locales,
			$this->env->conn,
			$this->types,
		)->run();

		echo
			"Node titles rebuilt: {$result['nodes']} node(s), "
				. "{$result['dynamic']} dynamic, {$result['empty']} without a title\n"
		;

		return 0;
	}
}
