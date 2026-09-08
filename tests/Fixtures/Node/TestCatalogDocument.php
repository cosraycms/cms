<?php

namespace Cosray\Tests\Fixtures\Node;

use Cosray\Block;
use Cosray\Field\Blocks;
use Cosray\Schema\Allows;
use Cosray\Schema\Columns;
use Cosray\Schema\Common;
use Cosray\Schema\Label;
use Cosray\Schema\Translate;
use Cosray\Schema\TranslateMode;
use Cosray\Tests\Fixtures\Block\CatalogBlock;

final class TestCatalogDocument
{
	#[Label('Main content'), Columns(12, min: 2), Translate]
	#[Common(CatalogBlock::class)]
	#[Allows(Block\Text::class, CatalogBlock::class, Block\Iframe::class)]
	private Blocks $story;

	#[Label('Translated content'), Translate(TranslateMode::Asymmetric)]
	private Blocks $translated;
}
