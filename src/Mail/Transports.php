<?php

declare(strict_types=1);

namespace Cosray\Mail;

use Closure;
use Cosray\Config;
use Cosray\Exception\RuntimeException;
use Psr\Log\LoggerInterface as Logger;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Mailer\EventListener\EnvelopeListener;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;

/**
 * The configured Symfony Mailer transports, built on first use. With
 * `mail.redirect`, every mail goes to those addresses instead of its
 * recipients; its headers stay as they are.
 *
 * @internal
 */
final class Transports
{
	private ?TransportInterface $transports = null;

	/**
	 * @param ?Closure(array<non-empty-string, non-empty-string>, EventDispatcher): TransportInterface $build
	 *        builds the transports instead of the DSNs, for tests; the dispatcher carries the redirect
	 */
	public function __construct(
		private readonly Config $config,
		private readonly Logger $logger,
		private readonly ?Closure $build = null,
	) {}

	public function has(string $name): bool
	{
		return array_key_exists($name, $this->config->mail->transports);
	}

	/** @param ?string $name the transport to send through, the default one if null */
	public function send(Email $email, ?string $name = null): void
	{
		if ($name !== null) {
			if (!$this->has($name)) {
				throw new RuntimeException("There is no mail transport named {$name}.");
			}

			$email->getHeaders()->addTextHeader('X-Transport', $name);
		}

		$this->transports()->send($email);
	}

	private function transports(): TransportInterface
	{
		if ($this->transports !== null) {
			return $this->transports;
		}

		$dsns = $this->config->mail->transports;

		if ($dsns === []) {
			throw new RuntimeException('No mail transport is configured: set MAILER_DSN or `mail.transports`.');
		}

		$dispatcher = new EventDispatcher();
		$redirect = $this->config->mail->redirect;

		if ($redirect !== []) {
			$dispatcher->addSubscriber(new EnvelopeListener(recipients: $redirect));
		}

		return $this->transports = $this->build !== null
			? ($this->build)($dsns, $dispatcher)
			: Transport::fromDsns($dsns, $dispatcher, logger: $this->logger);
	}
}
