<?php

declare(strict_types=1);

namespace Cosray\Mail;

use Cosray\Contract\Job;
use Cosray\Exception\JobFailed;
use Override;
use Psr\Log\LoggerInterface as Logger;
use Symfony\Component\Mime\Exception\RfcComplianceException;
use Throwable;

/**
 * Sends one queued mail. A transport error fails the attempt, so the
 * queue tries again later; a mail that cannot be read or addressed, or
 * whose transport is no longer configured, fails at once.
 *
 * @internal
 */
final class Delivery implements Job
{
	public function __construct(
		private readonly Transports $transports,
		private readonly Logger $logger,
	) {}

	#[Override]
	public function handle(array $payload): void
	{
		try {
			[$email, $transport] = Payload::decode($payload);
		} catch (Throwable $e) {
			throw new JobFailed("The queued mail cannot be read: {$e->getMessage()}", previous: $e);
		}

		if ($transport !== null && !$this->transports->has($transport)) {
			throw new JobFailed("There is no mail transport named {$transport}.");
		}

		try {
			$this->transports->send($email, $transport);
		} catch (RfcComplianceException $e) {
			throw new JobFailed($e->getMessage(), previous: $e);
		}

		$this->logger->info('Mail sent to {count} recipients through {transport}', [
			'count' => count($email->getTo()) + count($email->getCc()) + count($email->getBcc()),
			'transport' => $transport ?? 'the default transport',
		]);
	}
}
