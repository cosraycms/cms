<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Plugin;

use Cosray\Controller\Panel\Panel;

final class PageController extends Panel
{
	public function index(): array
	{
		return $this->context(['title' => 'Test Plugin Page']);
	}
}
