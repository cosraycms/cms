<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Node;

use Cosray\Api\Serializer;
use Cosray\Contract\Json;
use Cosray\Contract\Title;
use Cosray\Field\Text;
use Cosray\Schema\Label;
use Cosray\Schema\Route;

#[Label('Test JSON Page'), Route('/json/{uid}')]
final class TestJsonPage implements Json, Title
{
	#[Label('Title')]
	public Text $title;

	public function title(): string
	{
		return $this->title->value()->raw();
	}

	public function json(Serializer $serializer): mixed
	{
		return ['composed' => true, 'page' => $serializer->node($this, fields: ['title'])];
	}
}
