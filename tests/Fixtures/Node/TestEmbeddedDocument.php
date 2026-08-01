<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Node;

use Cosray\Contract\HasInit;
use Cosray\Field\Text;
use Cosray\Schema\Description;
use Cosray\Schema\Fieldset;
use Cosray\Schema\Label;
use Cosray\Schema\Width;

final class TestEmbeddedDocument implements HasInit
{
	#[Label('Before')]
	private Text $before;

	#[Fieldset]
	#[Label('Document fields')]
	#[Description('Reusable document fields')]
	#[Width(50)]
	private TestBaseFields $baseFields;

	#[Label('After')]
	private Text $after;

	public bool $initializedAfterEmbed = false;

	public function init(): void
	{
		$this->initializedAfterEmbed = $this->baseFields->initialized;
	}

	public function baseFields(): TestBaseFields
	{
		return $this->baseFields;
	}
}
