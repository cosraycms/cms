<?php

declare(strict_types=1);

namespace Cosray\User;

use Celema\Quma\Database;
use Celema\Verba\Verba;
use Cosray\Config;
use Cosray\Contract\Job;
use Cosray\Exception\JobFailed;
use Cosray\Locales;
use Cosray\Mailer;
use Cosray\Middleware\PanelLocale;
use Cosray\Security\Policy;
use Cosray\User;
use Cosray\Users;
use Cosray\Util\Transaction;
use Override;
use Psr\Log\LoggerInterface as Logger;
use Symfony\Component\Mime\Address;

/**
 * Sends a password reset link for a requested login, if it belongs to a
 * user with panel access. The request only queues this job, so it does the
 * same work whether or not the login exists.
 *
 * @internal
 */
final class PasswordResetMail implements Job
{
	public function __construct(
		private readonly PasswordResets $resets,
		private readonly Users $users,
		private readonly Policy $policy,
		private readonly Mailer $mailer,
		private readonly Locales $locales,
		private readonly Config $config,
		private readonly Database $db,
		private readonly Logger $logger,
	) {}

	#[Override]
	public function handle(array $payload): void
	{
		$login = $payload['login'] ?? null;
		$ip = $payload['ip'] ?? null;
		$locale = $payload['locale'] ?? null;

		if (!is_string($login) || !is_string($ip) || $locale !== null && !is_string($locale)) {
			throw new JobFailed('A password reset request needs a login, an IP address and a locale or null.');
		}

		$user = $this->users->byLogin($login);

		if ($user === null || !$this->policy->permits($user, 'panel')) {
			return;
		}

		// A mail that cannot be queued takes its link along, so the retry is not refused as too soon.
		$sent = Transaction::run($this->db, function () use ($user, $locale): bool {
			$token = $this->resets->issue($user);

			if ($token === null) {
				return false;
			}

			$this->send($user, $token, $locale);

			return true;
		});

		if ($sent) {
			$this->logger->notice('Password reset link sent to {login} for a request from {ip}', [
				'login' => $user->loginName(),
				'ip' => $ip,
			]);
		}
	}

	/** Sends the mail in the panel language the user chose, else in that of the request. */
	private function send(User $user, #[\SensitiveParameter] string $token, ?string $requested): void
	{
		$available = $this->locales->panelLocales();
		$locale = match (true) {
			in_array($user->panelLocale, $available, true) => $user->panelLocale,
			in_array($requested, $available, true) => $requested,
			default => null,
		};
		$previous = Verba::translator();

		if ($locale !== null) {
			Verba::activate(new PanelLocale($this->config)->translator($this->locales, $locale));
		}

		try {
			$this->mailer->send(
				'cosray/password-reset',
				to: new Address($user->email, $user->name ?? ''),
				data: [
					'name' => $user->name ?? $user->loginName(),
					'site' => $this->config->app->title,
					'url' => $this->resets->url($token),
				],
			);
		} finally {
			if ($previous !== null) {
				Verba::activate($previous);
			} else {
				Verba::deactivate();
			}
		}
	}
}
