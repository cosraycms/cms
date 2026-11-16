<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Plugin;

use Celema\Core\Factory\Factory;
use Celema\Core\Response;
use Cosray\Controller\Panel\Panel;

final class PageController extends Panel
{
	public function index(): array
	{
		return $this->context(['title' => 'Test Plugin Page', 'statsUrl' => $this->url('/stats', ['tab' => 'all'])]);
	}

	public function save(Factory $factory): Response
	{
		$name = $this->formData()['name'] ?? '';

		return Response::create($factory)->redirect($this->url('', ['saved' => is_string($name) ? $name : '']), 303);
	}
}
