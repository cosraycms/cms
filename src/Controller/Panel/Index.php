<?php

declare(strict_types=1);

namespace Celemas\Cms\Controller\Panel;

final class Index extends Panel
{
	public function index(): array
	{
		return $this->context();
	}
}
