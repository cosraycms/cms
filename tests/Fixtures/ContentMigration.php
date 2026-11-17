<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures;

use Celema\Quma\Contract\Migration;
use Celema\Quma\Environment;
use Cosray\Api\Writer as ApiWriter;
use Cosray\Cms;
use Cosray\Context;
use Cosray\Node\Writer;
use Override;

final class ContentMigration implements Migration
{
	public function __construct(
		public readonly Context $context,
		public readonly Cms $cms,
		public readonly Writer $writer,
		public readonly ApiWriter $api,
	) {}

	#[Override]
	public function run(Environment $env): void {}
}
