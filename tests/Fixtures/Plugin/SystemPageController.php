<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Plugin;

use Cosray\Controller\Panel\Panel;
use Cosray\Middleware\Permission;

final class SystemPageController extends Panel
{
	protected const string AREA = 'system';

	/** @return array<string, mixed> */
	#[Permission('test-plugin.view-stats')]
	public function index(): array
	{
		return $this->context(['title' => 'Test Stats']);
	}
}
