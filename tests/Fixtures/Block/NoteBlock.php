<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Block;

use Cosray\Block\RenderContext;
use Cosray\Contract\Block;
use Cosray\Field\Image;
use Cosray\Field\RichText;
use Cosray\Schema\FieldOrder;
use Cosray\Schema\Handle;
use Cosray\Schema\Label;
use Cosray\Schema\Limit;
use Cosray\Schema\Tool;
use Cosray\Schema\Tools;
use Cosray\Schema\Translate;
use Cosray\Schema\TranslateMode;
use Cosray\Value\Block as BlockValue;

/** Exercises handle, field order, the tools rule and per-field translation. */
#[Label('Note'), Handle('note'), FieldOrder('aside', 'body', 'cover')]
final class NoteBlock implements Block
{
	#[Label('Body'), Translate]
	protected RichText $body;

	#[Label('Aside'), Tools(Tool::MINIMAL)]
	protected RichText $aside;

	#[Label('Cover'), Limit(1), Translate(TranslateMode::Asymmetric)]
	protected Image $cover;

	public function render(BlockValue $block, RenderContext $ctx): string
	{
		return "<aside>{$block->body}</aside>";
	}
}
