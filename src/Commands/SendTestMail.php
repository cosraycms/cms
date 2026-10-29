<?php

declare(strict_types=1);

namespace Cosray\Commands;

use Celema\Console\Arg;
use Celema\Console\Command;
use Celema\Console\Io;
use Celema\Console\Opt;
use Cosray\Config;
use Cosray\Mail\Transports;
use Symfony\Component\Mime\Email;
use Throwable;

#[Command('mail:test', 'Sends a test mail right away, past the queue', group: 'Mail')]
final class SendTestMail
{
	public function __construct(
		private readonly Config $config,
		private readonly Transports $transports,
	) {}

	public function __invoke(
		Io $io,
		#[Arg('The address to send the test mail to')]
		string $to,
		#[Opt('The transport to send through; the default one if empty')]
		string $transport = '',
	): int {
		$from = $this->config->mail->from;

		if ($from === null) {
			$io->error('Set MAILER_FROM or <strong>mail.from</strong> first.');

			return 1;
		}

		try {
			$this->transports->send(
				new Email()
					->from($from)
					->to($to)
					->subject("Test mail from {$this->config->app->title}")
					->text("This mail tests the mail setup of {$this->config->app->title}."),
				$transport === '' ? null : $transport,
			);
		} catch (Throwable $e) {
			$io->error('The test mail was not sent: %s', $e->getMessage());

			return 1;
		}

		$redirect = $this->config->mail->redirect;

		if ($redirect === []) {
			$io->success('Sent a test mail to %s.', $to);
		} else {
			$io->success('Sent a test mail to %s, delivered to %s by mail.redirect.', $to, implode(', ', $redirect));
		}

		return 0;
	}
}
