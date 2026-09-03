<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Block;

use Cosray\Block\RenderContext;
use Cosray\Config;
use Cosray\Contract\Block;
use Cosray\Field\Text;
use Cosray\Schema\Label;
use Cosray\Value\Block as BlockValue;

/** A block type with an autowired constructor dependency. */
#[Label('Service')]
final class ServiceBlock implements Block
{
	public function __construct(
		private readonly Config $config,
	) {}

	#[Label('Text')]
	protected Text $text;

	public function render(BlockValue $block, RenderContext $ctx): string
	{
		return $this->config->get('app.name') . ': ' . $block->text;
	}
}
