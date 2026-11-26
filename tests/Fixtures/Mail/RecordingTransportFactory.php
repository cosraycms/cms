<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Mail;

use Override;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

/** Builds a RecordingTransport for `recording://` DSNs and keeps the last one. */
final class RecordingTransportFactory extends AbstractTransportFactory
{
	public static ?RecordingTransport $transport = null;

	#[Override]
	public function create(Dsn $dsn): TransportInterface
	{
		return self::$transport = new RecordingTransport($this->dispatcher, $this->logger);
	}

	/** @return list<string> */
	#[Override]
	protected function getSupportedSchemes(): array
	{
		return ['recording'];
	}
}
