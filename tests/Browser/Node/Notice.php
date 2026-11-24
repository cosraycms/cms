<?php

declare(strict_types=1);

namespace Cosray\Tests\Browser\Node;

use Cosray\Contract\Title;
use Cosray\Field\Image;
use Cosray\Field\RichText;
use Cosray\Field\Text;
use Cosray\Schema\Label;
use Cosray\Schema\Limit;
use Cosray\Schema\Required;

#[Label('Notice')]
class Notice implements Title
{
	#[Label('Title')]
	protected Text $title;

	#[Label('Body'), Required]
	protected RichText $body;

	#[Label('Picture'), Limit(1), Required]
	protected Image $picture;

	public function title(): string
	{
		return $this->title?->value()->raw() ?? '';
	}
}
