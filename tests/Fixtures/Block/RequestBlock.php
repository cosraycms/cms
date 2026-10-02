<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Block;

use Celema\Core\Request;
use Cosray\Block\RenderContext;
use Cosray\Contract\Block;
use Cosray\Field\Owner;
use Cosray\Tests\Fixtures\Context\RequestService;
use Cosray\Value\Block as BlockValue;

/** A block type that depends on the request it is rendered for. */
final class RequestBlock implements Block
{
	public function __construct(
		public readonly Owner $owner,
		public readonly Request $request,
		public readonly RequestService $service,
	) {}

	public function render(BlockValue $block, RenderContext $ctx): string
	{
		return '';
	}
}
