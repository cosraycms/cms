<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Node;

use Cosray\Contract\Title;
use Cosray\Field\DateTime;
use Cosray\Field\Text;
use Cosray\Schema\Label;
use Cosray\Schema\Required;

#[Label('Date/time document')]
final class TestDateTimeDocument implements Title
{
	#[Required]
	public Text $title;

	#[Required]
	public DateTime $observedAt;

	public function title(): string
	{
		return ($this->title?->value()->unwrap() ?? 'Untitled') . ' · ' . $this->observedAt->value()->format('c');
	}
}
