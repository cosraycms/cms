<?php

declare(strict_types=1);

namespace Cosray\Commands;

use Celema\Console\Arg;
use Celema\Console\Command;
use Celema\Console\Io;
use Celema\Console\Opt;
use Cosray\Config;
use Cosray\Security\Policy;
use Cosray\Users;
use Psr\Log\LoggerInterface as Logger;

#[Command('user:login-link', 'Prints a link that signs a user into the panel once', group: 'Users')]
final class LoginLink
{
	public function __construct(
		private readonly Users $users,
		private readonly Policy $policy,
		private readonly Config $config,
		private readonly Logger $logger,
	) {}

	public function __invoke(
		Io $io,
		#[Arg('Email address or username of the user')]
		string $login,
		#[Opt('The panel page to open, relative to the panel, like /node/abc')]
		string $next = '',
	): int {
		$user = $this->users->byLogin($login);

		if ($user === null) {
			$io->error('No active user with the login <strong>%s</strong>.', $login);

			return 1;
		}

		if (!$this->policy->permits($user, 'panel')) {
			$io->error('%s has no panel access.', $user->loginName());

			return 1;
		}

		if ($next !== '' && (!str_starts_with($next, '/') || str_starts_with($next, '//'))) {
			$io->error('--next is a path inside the panel, like /node/abc.');

			return 1;
		}

		$panel = rtrim($this->config->panel->path, '/');
		$url =
			($this->config->app->url ?? $this->config->app->urlPrefix)
			. $panel
			. '/handoff/'
			. $this->users->createOneTimeToken($user->id);

		if ($next !== '') {
			$url .= '?' . http_build_query(['next' => $panel . $next], '', '&', PHP_QUERY_RFC3986);
		}

		$this->logger->notice('Sign-in link for {login} created from the console', ['login' => $user->loginName()]);

		if ($this->config->app->url === null) {
			$io->warn('Without app.url the link lacks the site address; put it in front.');
		}

		$io->line('%s', $url);
		$io->line('It signs %s in once, within five minutes.', $user->loginName());

		return 0;
	}
}
