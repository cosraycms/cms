<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Node;

use Cosray\Schema\Description;
use Cosray\Schema\Fieldset;
use Cosray\Schema\Label;
use Cosray\Schema\Width;

#[Label('Test Embedded Entry')]
final class TestEmbeddedEntry
{
	#[Fieldset]
	#[Label('Entry base fields')]
	#[Description('Reusable entry fields')]
	#[Width(50)]
	private TestBaseFields $baseFields;
}
