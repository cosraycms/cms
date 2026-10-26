<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Exception\RuntimeException;
use Cosray\Mail\Payload;
use Cosray\Tests\TestCase;
use DateTimeImmutable;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

/**
 * @internal
 *
 * @coversNothing
 */
final class MailPayloadTest extends TestCase
{
	public function testBodiesFromStreamsAreReadFromTheirStart(): void
	{
		$text = fopen('php://memory', 'r+');
		$html = fopen('php://memory', 'r+');
		$this->assertIsResource($text);
		$this->assertIsResource($html);
		fwrite($text, 'Hello');
		fwrite($html, '<p>Hello</p>');
		$email = new Email()
			->from('site@example.com')
			->to('ann@example.com')
			->subject('Hi')
			->text($text)
			->html($html);

		[$queued] = Payload::decode(Payload::encode($email, null));

		$this->assertSame('Hello', $queued->getTextBody());
		$this->assertSame('<p>Hello</p>', $queued->getHtmlBody());
	}

	public function testAMailSurvivesTheQueue(): void
	{
		$email = new Email()
			->from(new Address('site@example.com', 'Site'))
			->sender('bounce@example.com')
			->returnPath('bounce@example.com')
			->to('ann@example.com', new Address('bob@example.com', 'Bob Müller'))
			->cc('cc@example.com')
			->bcc('bcc@example.com')
			->replyTo('reply@example.com')
			->subject('Grüße')
			->text('Hello')
			->html('<p>Hello <img src="cid:logo.png"></p>')
			->priority(Email::PRIORITY_HIGH)
			->attach('%PDF-1.7', 'receipt.pdf', 'application/pdf')
			->embed("\x89PNG", 'logo.png', 'image/png');
		$email->getHeaders()->addTextHeader('X-Campaign', 'spring');

		// The queue stores the payload as JSON.
		$payload = json_decode(json_encode(Payload::encode($email, 'newsletter'), JSON_THROW_ON_ERROR), true);
		[$queued, $transport] = Payload::decode($payload);

		$this->assertSame('newsletter', $transport);
		$this->assertSame(
			['site@example.com'],
			array_map(static fn(Address $a): string => $a->getAddress(), $queued->getFrom()),
		);
		$this->assertSame('Site', $queued->getFrom()[0]->getName());
		$this->assertSame('bounce@example.com', $queued->getSender()?->getAddress());
		$this->assertSame('bounce@example.com', $queued->getReturnPath()?->getAddress());
		$this->assertSame('Bob Müller', $queued->getTo()[1]->getName());
		$this->assertSame('cc@example.com', $queued->getCc()[0]->getAddress());
		$this->assertSame('bcc@example.com', $queued->getBcc()[0]->getAddress());
		$this->assertSame('reply@example.com', $queued->getReplyTo()[0]->getAddress());
		$this->assertSame('Grüße', $queued->getSubject());
		$this->assertSame('Hello', $queued->getTextBody());
		$this->assertSame('<p>Hello <img src="cid:logo.png"></p>', $queued->getHtmlBody());
		$this->assertSame(Email::PRIORITY_HIGH, $queued->getPriority());
		$this->assertSame('spring', $queued->getHeaders()->getHeaderBody('X-Campaign'));
		$this->assertSame(
			[
				['receipt.pdf', 'application/pdf', '%PDF-1.7', 'attachment'],
				['logo.png', 'image/png', "\x89PNG", 'inline'],
			],
			array_map(
				static fn(DataPart $part): array => [
					$part->getFilename(),
					$part->getContentType(),
					$part->getBody(),
					$part->getDisposition(),
				],
				$queued->getAttachments(),
			),
		);
		// The embedded image is referenced by its content id once the mail is put together.
		$this->assertStringContainsString('cid:' . $queued->getAttachments()[1]->getContentId(), $queued->toString());
	}

	public function testOnlyWhatTheMailHasComesBack(): void
	{
		[$queued] = Payload::decode(Payload::encode(new Email()->to('ann@example.com')->text('Hi')));

		$this->assertFalse($queued->getHeaders()->has('X-Priority'));
		$this->assertFalse($queued->getHeaders()->has('From'));
		$this->assertFalse($queued->getHeaders()->has('Cc'));
		$this->assertNull($queued->getHtmlBody());
	}

	public function testWhatThePayloadCannotCarryIsRefusedWhenQueueing(): void
	{
		$dated = new Email()->text('Hi');
		$dated->getHeaders()->addDateHeader('X-Sent', new DateTimeImmutable());
		$this->assertRefused($dated, 'text headers only, not X-Sent');

		$this->assertRefused(new Email()->text('Hi', 'iso-8859-1'), 'UTF-8 bodies, not iso-8859-1');
		$this->assertRefused(
			new Email()
				->text('Hi')
				->attach(str_repeat('a', Payload::MAX_ATTACHMENTS + 1), 'big.bin'),
			'at most 10 MiB of attachments',
		);
	}

	public function testAnAttachmentThatIsNotBase64CannotBeRead(): void
	{
		$payload = Payload::encode(
			new Email()
				->text('Hi')
				->attach('data', 'a.txt'),
		);
		$payload['attachments'][0]['body'] = 'not base64!';

		$this->throws(RuntimeException::class, 'not base64');

		Payload::decode($payload);
	}

	public function testAPayloadOfTheWrongShapeCannotBeRead(): void
	{
		$payload = Payload::encode(
			new Email()
				->text('Hi')
				->attach('data'),
		);
		unset($payload['attachments'][0]['type']);

		$this->throws(RuntimeException::class, 'lacks keys or has values of the wrong type');

		Payload::decode($payload);
	}

	private function assertRefused(Email $email, string $message): void
	{
		try {
			Payload::encode($email);
			$this->fail('The mail was encoded.');
		} catch (RuntimeException $e) {
			$this->assertStringContainsString($message, $e->getMessage());
		}
	}
}
