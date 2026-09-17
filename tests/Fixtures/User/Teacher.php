<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\User;

use Cosray\Field\Text;
use Cosray\Schema\Label;
use Cosray\Schema\Required;
use Cosray\Schema\Roles;
use Cosray\User;

#[Label('Teacher'), Roles('editor')]
class Teacher extends User
{
	#[Label('Subject'), Required]
	protected Text $subject;

	#[Label('Room')]
	protected Text $room;

	public function subject(): string
	{
		return (string) $this->subject->value();
	}
}
