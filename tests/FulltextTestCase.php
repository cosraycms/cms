<?php

declare(strict_types=1);

namespace Cosray\Tests;

use Celema\Quma\Query;
use Cosray\Block\Registry;
use Cosray\Fulltext\Builder;
use Cosray\Fulltext\Sync;
use Cosray\Node\Types;

abstract class FulltextTestCase extends IntegrationTestCase
{
	protected function sync(): Sync
	{
		return new Sync($this->db(), new Builder(new Types(), Registry::withDefaults()));
	}

	protected function sql(string $name, array $params = []): Query
	{
		return $this->db()->execute(
			file_get_contents(self::root() . "/tests/Fixtures/sql/fulltext/{$name}.sql"),
			$params,
		);
	}
}
