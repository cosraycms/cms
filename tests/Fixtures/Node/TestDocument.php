<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Node;

use Cosray\Field\Text;
use Cosray\Field\Textarea;
use Cosray\Node\Contract\Title;
use Cosray\Schema\DefaultValue;
use Cosray\Schema\Description;
use Cosray\Schema\Hidden;
use Cosray\Schema\Immutable;
use Cosray\Schema\Label;
use Cosray\Schema\Required;
use Cosray\Schema\Rows;
use Cosray\Schema\Translate;
use Cosray\Schema\Validate;
use Cosray\Schema\Width;

#[Label('Test Document')]
class TestDocument implements Title
{
	#[Label('Document Title')]
	#[Required]
	#[Validate('minlen:3', 'maxlen:100')]
	public Text $title;

	#[Label('Introduction')]
	#[Description('A brief introduction to the document')]
	#[Rows(5)]
	#[Width(12)]
	#[Translate]
	public Textarea $intro;

	#[Hidden]
	#[Immutable]
	#[DefaultValue('auto-generated-id')]
	public Text $internalId;

	public function title(): string
	{
		return $this->title?->value()->unwrap() ?? 'Test Document';
	}
}
