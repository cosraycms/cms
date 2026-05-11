<?php

declare(strict_types=1);

namespace Celemas\Cms\Tests\Fixtures\Node;

use Celemas\Cms\Field\Grid;
use Celemas\Cms\Field\Image;
use Celemas\Cms\Field\Option;
use Celemas\Cms\Field\Video;
use Celemas\Cms\Node\Contract\Title;
use Celemas\Cms\Schema\Columns;
use Celemas\Cms\Schema\Label;
use Celemas\Cms\Schema\Options;
use Celemas\Cms\Schema\Translate;
use Celemas\Cms\Schema\TranslateFile;

#[Label('Test Media Document')]
class TestMediaDocument implements Title
{
	#[Label('Gallery')]
	#[TranslateFile]
	public Image $gallery;

	#[Label('Video')]
	#[TranslateFile]
	public Video $video;

	#[Label('Content Grid')]
	#[Columns(12, 2)]
	#[Translate]
	public Grid $contentGrid;

	#[Label('Category')]
	#[Options(['news', 'blog', 'tutorial'])]
	public Option $category;

	public function title(): string
	{
		return 'Test Media Document';
	}
}
