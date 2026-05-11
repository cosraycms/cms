<?php

declare(strict_types=1);

namespace Celemas\Cms\Tests\Fixtures\Node;

use Celemas\Cms\Schema\Icon;
use Celemas\Cms\Schema\Label;

#[Label('Node with icon')]
#[Icon('bi:check', color: '#ff0000', class: 'cms-node-icon', style: 'height: 1rem')]
class NodeWithIconAttribute {}
