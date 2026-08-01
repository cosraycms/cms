<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Node;

use Cosray\Contract\Embedded;
use Cosray\Contract\Title;
use Cosray\Field\Text;

final class TestAlternateTitleFields implements Embedded, Title
{
	private Text $alternate;

	public function title(): string
	{
		return $this->alternate->value()->unwrap() ?? '';
	}
}
