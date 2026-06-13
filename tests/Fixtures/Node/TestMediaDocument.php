<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Node;

use Cosray\Field\Blocks;
use Cosray\Field\Image;
use Cosray\Field\Option;
use Cosray\Field\Video;
use Cosray\Node\Contract\Title;
use Cosray\Schema\Columns;
use Cosray\Schema\Label;
use Cosray\Schema\Options;
use Cosray\Schema\Translate;
use Cosray\Schema\TranslateMode;

#[Label('Test Media Document')]
class TestMediaDocument implements Title
{
	#[Label('Gallery')]
	#[Translate(TranslateMode::Asymmetric)]
	public Image $gallery;

	#[Label('Video')]
	#[Translate(TranslateMode::Asymmetric)]
	public Video $video;

	#[Label('Content Blocks')]
	#[Columns(12, 2)]
	#[Translate(TranslateMode::Asymmetric)]
	public Blocks $contentBlocks;

	#[Label('Category')]
	#[Options(['news', 'blog', 'tutorial'])]
	public Option $category;

	public function title(): string
	{
		return 'Test Media Document';
	}
}
