<?php

declare(strict_types=1);

namespace Cosray\Controller\Panel;

use Cosray\Locales;
use Cosray\Panel\System;

final class Media extends Panel
{
	public function index(): array
	{
		$locales = $this->container->get(Locales::class);
		assert($locales instanceof Locales, 'The locales service must be available');

		return $this->context([
			'system' => new System($this->config, $locales)->payload(),
		]);
	}
}
