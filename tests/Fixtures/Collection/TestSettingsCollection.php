<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Collection;

use Cosray\Schema\Handle;
use Cosray\Schema\Label;
use Cosray\Schema\Types;

#[Label('Test settings'), Handle('test-settings'), Types('test-setting')]
final class TestSettingsCollection {}
