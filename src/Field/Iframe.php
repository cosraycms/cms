<?php

declare(strict_types=1);

namespace Cosray\Field;

use Cosray\Value\Iframe as IframeValue;
use Override;

class Iframe extends Text implements Capability\Multiline
{
	use Capability\IsMultiline;

	#[Override]
	public function control(): Control
	{
		return Control::iframe();
	}

	#[Override]
	public function value(): IframeValue
	{
		return new IframeValue($this->owner, $this, $this->valueContext);
	}

	#[Override]
	public function structure(mixed $value = null): array
	{
		$result = $this->getTranslatableStructure('iframe', $value);
		$result['meta']['iframeWidth'] = [self::NEUTRAL_LOCALE => '100%'];
		$result['meta']['iframeHeight'] = [self::NEUTRAL_LOCALE => '75%'];

		return $result;
	}
}
