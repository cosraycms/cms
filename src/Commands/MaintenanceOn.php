<?php

declare(strict_types=1);

namespace Cosray\Commands;

use Celema\Console\Command;
use Celema\Console\Io;
use Celema\Console\Opt;
use Cosray\Config;
use Cosray\Maintenance;
use Cosray\Middleware\MaintenanceMode;
use SensitiveParameter;
use Throwable;

#[Command('maintenance:on', 'Refuses writes, or every request, until maintenance:off', group: 'Maintenance')]
final class MaintenanceOn
{
	public function __construct(
		private readonly Config $config,
		private readonly Maintenance $maintenance,
	) {}

	public function __invoke(
		Io $io,
		#[Opt('Refuse reads too, not only writes')]
		bool $full = false,
		#[Opt('Seconds clients are told to wait, sent as Retry-After')]
		int $retry = 60,
		#[Opt('A message for the error page and API clients')]
		string $message = '',
	): int {
		try {
			$secret = $this->maintenance->on($full, $retry, $message);
		} catch (Throwable $e) {
			$io->error($e->getMessage());

			return 1;
		}

		$io->success(
			$full
				? 'Maintenance mode is on: every request is refused.'
				: 'Maintenance mode is on: writes are refused, reads are served.',
		);
		$io->line('Open this link to pass in your browser: %s', $this->bypassUrl($secret));

		return 0;
	}

	private function bypassUrl(#[SensitiveParameter] string $secret): string
	{
		$base = rtrim((string) $this->config->app->url, '/') . rtrim($this->config->app->urlPrefix, '/');

		return $base . MaintenanceMode::bypassPath($this->config) . '?bypass=' . $secret;
	}
}
