<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Collection;

use Cosray\Schema\Blueprints;
use Cosray\Schema\Handle;
use Cosray\Schema\Label;
use Cosray\Schema\Types;
use Cosray\Tests\Fixtures\Node\TestDraftPage;

#[Label('Test draft pages'), Handle('test-draft-pages'), Types('test-draft-page'), Blueprints(TestDraftPage::class)]
final class TestDraftPagesCollection {}
