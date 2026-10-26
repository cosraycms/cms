<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Mail;

use Override;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Throwable;

/** Keeps the messages it sends, after the dispatcher's listeners had their turn. */
final class RecordingTransport extends AbstractTransport
{
	/** @var list<SentMessage> */
	public array $sent = [];

	public ?Throwable $failure = null;

	#[Override]
	protected function doSend(SentMessage $message): void
	{
		if ($this->failure !== null) {
			throw $this->failure;
		}

		$this->sent[] = $message;
	}

	#[Override]
	public function __toString(): string
	{
		return 'recording://';
	}
}
