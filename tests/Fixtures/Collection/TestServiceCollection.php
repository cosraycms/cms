<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Collection;

use Cosray\Column;
use Cosray\Config;
use Cosray\Contract\Columns;
use Cosray\Schema\Handle;
use Cosray\Schema\Label;
use Cosray\Schema\Types;

/** Builds its columns from an autowired constructor dependency. */
#[Label('Service entries'), Handle('test-service'), Types('test-article')]
final class TestServiceCollection implements Columns
{
	public function __construct(
		private readonly Config $config,
	) {}

	public function columns(): array
	{
		return [
			Column::new('Listed in ' . $this->config->app->timezone->getName(), 'title')->sort('title'),
		];
	}
}
