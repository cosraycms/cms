<?php

declare(strict_types=1);

namespace Celemas\Cms\Tests\Fixtures\Node;

use Celemas\Cms\Field\Text;
use Celemas\Cms\Schema\Icon;

class NodeWithFieldIconAttribute
{
	#[Icon('bi:type', ['color' => '#00ff00', 'class' => 'cms-field-icon', 'style' => 'width: 1rem'])]
	protected Text $title;
}
