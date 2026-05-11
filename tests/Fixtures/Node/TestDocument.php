<?php

declare(strict_types=1);

namespace Celemas\Cms\Tests\Fixtures\Node;

use Celemas\Cms\Field\Text;
use Celemas\Cms\Field\Textarea;
use Celemas\Cms\Node\Contract\Title;
use Celemas\Cms\Schema\DefaultValue;
use Celemas\Cms\Schema\Description;
use Celemas\Cms\Schema\Hidden;
use Celemas\Cms\Schema\Immutable;
use Celemas\Cms\Schema\Label;
use Celemas\Cms\Schema\Required;
use Celemas\Cms\Schema\Rows;
use Celemas\Cms\Schema\Translate;
use Celemas\Cms\Schema\Validate;
use Celemas\Cms\Schema\Width;

#[Label('Test Document')]
class TestDocument implements Title
{
	#[Label('Document Title')]
	#[Required]
	#[Validate('minLength:3', 'maxLength:100')]
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
