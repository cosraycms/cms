<?php

declare(strict_types=1);

namespace Cosray\Field;

use Cosray\Value\Iframe as IframeValue;

class Iframe extends Text
{
	public function value(): IframeValue
	{
		return new IframeValue($this->owner, $this, $this->valueContext);
	}

	public function structure(mixed $value = null): array
	{
		$result = $this->getTranslatableStructure('iframe', $value);
		$result['meta']['iframeWidth'] = [self::NEUTRAL_LOCALE => '100%'];
		$result['meta']['iframeHeight'] = [self::NEUTRAL_LOCALE => '75%'];

		return $result;
	}
}
