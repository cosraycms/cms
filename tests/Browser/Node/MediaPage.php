<?php

declare(strict_types=1);

namespace Cosray\Tests\Browser\Node;

use Cosray\Contract\Title;
use Cosray\Field\Image;
use Cosray\Field\Text;
use Cosray\Field\Video;
use Cosray\Schema\Alt;
use Cosray\Schema\Attribution;
use Cosray\Schema\Caption;
use Cosray\Schema\Label;
use Cosray\Schema\Limit;
use Cosray\Schema\Link;
use Cosray\Schema\Render;
use Cosray\Schema\Route;

#[Label('Media page'), Route('/{title}'), Render('media-page')]
class MediaPage implements Title
{
	#[Label('Title')]
	protected Text $title;

	#[Label('Cover'), Limit(1), Caption, Attribution, Link]
	protected Image $cover;

	#[Label('Gallery')]
	protected Image $gallery;

	#[Label('Strict'), Limit(1), Alt(required: true)]
	protected Image $strict;

	#[Label('Clip'), Limit(1)]
	protected Video $clip;

	public function title(): string
	{
		return $this->title?->value()->raw() ?? '';
	}
}
