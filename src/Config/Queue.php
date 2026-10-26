<?php

declare(strict_types=1);

namespace Cosray\Config;

final class Queue
{
	public function __construct(
		private readonly \Cosray\Config $config,
	) {}

	/** Whether each request runs due jobs once its response is sent. */
	public bool $afterResponse {
		get => (bool) $this->config->get('queue.after_response');
	}
}
