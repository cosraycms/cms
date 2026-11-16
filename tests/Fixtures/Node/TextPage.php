<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Node;

use Cosray\Field\RichText;
use Cosray\Field\Text;
use Cosray\Schema\Route;
use Cosray\Schema\Translate;

#[Route('/text/{uid}')]
class TextPage
{
	#[Translate]
	protected Text $title;

	#[Translate]
	protected RichText $body;
}
