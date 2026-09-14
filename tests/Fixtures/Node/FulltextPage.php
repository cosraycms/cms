<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Node;

use Cosray\Contract\Title;
use Cosray\Field\Text;
use Cosray\Field\Textarea;
use Cosray\Schema\Fulltext;
use Cosray\Schema\FulltextWeight;
use Cosray\Schema\Route;
use Cosray\Schema\Translate;

#[Route('/fulltext/{uid}')]
final class FulltextPage implements Title
{
	#[Translate]
	protected Text $name;

	#[Fulltext(FulltextWeight::D), Translate]
	protected Textarea $body;

	protected Text $category;
	protected Text $private;

	#[Fulltext(FulltextWeight::A)]
	public function title(): string
	{
		return $this->name->value()->unwrap();
	}
}
