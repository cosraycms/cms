<?php

declare(strict_types=1);

namespace Cosray\Controller\Panel;

final class Index extends Panel
{
	protected const string AREA = 'dashboard';

	public function index(): array
	{
		return $this->context();
	}
}
