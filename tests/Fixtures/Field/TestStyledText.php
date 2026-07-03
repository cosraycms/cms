<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Field;

use Cosray\Field\Control;
use Cosray\Field\Text;

/** A text field exposing user-editable meta through metaControl(). */
class TestStyledText extends Text
{
	public function metaControl(): ?Control
	{
		return Control::group([
			['key' => 'cssClass', 'label' => 'CSS class', 'control' => Control::text()],
			[
				'key' => 'tone',
				'control' => Control::option()->prop('options', ['calm', 'loud']),
			],
		]);
	}
}
