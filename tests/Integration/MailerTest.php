<?php

declare(strict_types=1);

namespace Cosray\Tests\Integration;

use Celema\Console\Buffer;
use Celema\Console\Io;
use Celema\Container\Container;
use Cosray\Commands\SendTestMail;
use Cosray\Config;
use Cosray\Context;
use Cosray\Exception\JobFailed;
use Cosray\Exception\RuntimeException;
use Cosray\Locales;
use Cosray\Mail\Delivery;
use Cosray\Mail\Payload;
use Cosray\Mail\Transports;
use Cosray\Mailer;
use Cosray\Queue;
use Cosray\Queue\Runner;
use Cosray\Tests\Fixtures\Mail\RecordingTransport;
use Cosray\Tests\Fixtures\RecordingLogger;
use Cosray\Tests\IntegrationTestCase;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Throwable;

/**
 * @internal
 *
 * @coversNothing
 */
final class MailerTest extends IntegrationTestCase
{
	private RecordingTransport $transport;
	private RecordingLogger $logger;
	private ?Throwable $failure = null;

	protected function setUp(): void
	{
		parent::setUp();

		$this->transport = new RecordingTransport();
		$this->logger = new RecordingLogger();
		$this->db()->execute('DELETE FROM cms.jobs')->run();
	}

	public function testATemplateIsRenderedQueuedAndSent(): void
	{
		$id = $this->mailer()->send(
			'contact',
			to: ['team@example.com'],
			data: ['name' => 'Ann <3', 'email' => 'ann@example.com', 'message' => 'Hello'],
			replyTo: 'ann@example.com',
		);

		$this->assertSame(
			['queue' => 'mail', 'job' => Delivery::class],
			$this->db()->execute('SELECT queue, job FROM cms.jobs WHERE id = :id', ['id' => $id])->one(),
		);
		$this->assertSame([], $this->transport->sent);

		$this->deliver();

		$email = $this->sent()[0];
		$this->assertSame('Message from Ann <3', $email->getSubject());
		$this->assertSame('site@example.com', $email->getFrom()[0]->getAddress());
		$this->assertSame('Site', $email->getFrom()[0]->getName());
		$this->assertSame(['team@example.com'], self::addresses($email->getTo()));
		$this->assertSame(['ann@example.com'], self::addresses($email->getReplyTo()));
		$this->assertStringContainsString('<p>Ann &lt;3 wrote:</p>', (string) $email->getHtmlBody());
		$this->assertSame(
			"New message\n\nAnn <3 wrote:\n\nHello\n\n"
				. 'Reply to ann@example.com or see the inbox (https://example.com/inbox).',
			$email->getTextBody(),
		);
		$this->assertSame('Mail sent to 1 recipients through the default transport', $this->logger->records[0][1]);
	}

	public function testATextTemplateTakesThePlaceOfTheDerivedText(): void
	{
		$this->mailer()->send('receipt', to: 'ann@example.com', data: ['name' => 'Ann & Co']);
		$this->deliver();

		$this->assertSame("Thanks, Ann & Co.\n", $this->sent()[0]->getTextBody());
	}

	public function testAComposedMailIsCompletedAndSent(): void
	{
		$email = $this
			->mailer()
			->compose('receipt', ['name' => 'Ann'])
			->attach('%PDF-1.7', 'receipt.pdf', 'application/pdf');

		$this->mailer()->send($email, to: 'ann@example.com', bcc: ['office@example.com'], subject: 'Your receipt');
		$this->deliver();

		$sent = $this->sent()[0];
		$this->assertSame('Your receipt', $sent->getSubject());
		$this->assertSame(['office@example.com'], self::addresses($sent->getBcc()));
		$this->assertSame(
			['receipt.pdf'],
			array_map(static fn(DataPart $part): ?string => $part->getFilename(), $sent->getAttachments()),
		);
	}

	public function testAMailGoesThroughTheTransportItNames(): void
	{
		$this->mailer()->via('newsletter')->send('receipt', to: 'ann@example.com', data: ['name' => 'Ann']);
		$this->deliver();

		$this->assertSame('newsletter', $this->sent()[0]->getHeaders()->getHeaderBody('X-Transport'));

		$this->throws(RuntimeException::class, 'no mail transport named weekly');

		$this->mailer()->via('weekly');
	}

	public function testARedirectDeliversEveryMailToItsAddressesOnly(): void
	{
		$config = $this->mailConfig(['mail.redirect' => 'dev@example.com, qa@example.com']);

		$this->mailer($config)->send('receipt', to: 'customer@example.com', data: ['name' => 'Ann']);
		$this->deliver($config);

		$message = $this->transport->sent[0];
		$this->assertSame(
			['dev@example.com', 'qa@example.com'],
			self::addresses($message->getEnvelope()->getRecipients()),
		);
		$this->assertSame(['customer@example.com'], self::addresses($this->sent()[0]->getTo()));
	}

	public function testATemplateRendersInTheLocaleItIsAskedFor(): void
	{
		$this->assertSame('Hola', $this->mailer()->compose('greeting', locale: 'es')->getSubject());
		$this->assertSame('Hello', $this->mailer()->compose('greeting', locale: 'en')->getSubject());
	}

	public function testSendRefusesMailsThatCouldNotBeDelivered(): void
	{
		$mailer = $this->mailer();

		$this->assertRefused(
			fn(): int => $this->mailer($this->config())->send('receipt', to: 'ann@example.com'),
			'No mail transport is configured',
		);
		$this->assertRefused(
			static fn(): int => $mailer->send(new Email()->text('Hi'), data: ['name' => 'Ann']),
			'Data and locale are for templates',
		);
		$this->assertRefused(
			static fn(): int => $mailer->send('untitled', to: 'ann@example.com'),
			'A mail needs a subject',
		);
		$this->assertRefused(
			static fn(): int => $mailer->send('receipt', data: ['name' => 'Ann']),
			'must have a "To", "Cc", or "Bcc"',
		);
		$this->assertRefused(
			fn(): int => $this->mailer($this->mailConfig(['mail.from' => null]))->send(
				'receipt',
				to: 'ann@example.com',
				data: ['name' => 'Ann'],
			),
			'A mail needs a sender',
		);
		$this->assertSame(0, $this->db()->execute('SELECT count(*) AS n FROM cms.jobs')->one()['n']);
	}

	public function testADeliveryThatCannotSucceedFailsAndOneThatMightRunsAgain(): void
	{
		$delivery = new Delivery($this->transports($this->mailConfig()), $this->logger);
		$payload = Payload::encode(
			new Email()
				->from('site@example.com')
				->to('ann@example.com')
				->subject('Hi')
				->text('Hi'),
			'weekly',
		);

		try {
			$delivery->handle($payload);
			$this->fail('The mail was sent.');
		} catch (JobFailed $e) {
			$this->assertSame('There is no mail transport named weekly.', $e->getMessage());
		}

		try {
			$delivery->handle(['to' => 'garbage']);
			$this->fail('The mail was sent.');
		} catch (JobFailed $e) {
			$this->assertStringContainsString('The queued mail cannot be read', $e->getMessage());
		}

		$this->failure = new TransportException('Connection refused');
		$this->throws(TransportException::class, 'Connection refused');

		$delivery->handle(['transport' => null] + $payload);
	}

	public function testTheTestCommandSendsRightAway(): void
	{
		$config = $this->mailConfig();
		$output = new Buffer();

		$this->assertSame(0, (new SendTestMail($config, $this->transports($config)))(
			new Io($output),
			'admin@example.com',
		));
		$this->assertStringContainsString('Sent a test mail to admin@example.com.', $output->output());
		$this->assertSame(['admin@example.com'], self::addresses($this->sent()[0]->getTo()));

		$this->failure = new TransportException('Authentication failed');
		$failed = new Buffer();
		$this->assertSame(1, (new SendTestMail($config, $this->transports($config)))(
			new Io($failed),
			'admin@example.com',
		));
		$this->assertStringContainsString('The test mail was not sent: Authentication failed', $failed->errorOutput());

		$senderless = $this->mailConfig(['mail.from' => null]);
		$this->assertSame(1, (new SendTestMail($senderless, $this->transports($senderless)))(
			new Io(new Buffer()),
			'admin@example.com',
		));
	}

	/** @param array<string, mixed> $settings */
	private function mailConfig(array $settings = []): Config
	{
		return $this->config([
			'mail.transports' => ['default' => 'null://null', 'newsletter' => 'null://null'],
			'mail.from' => 'Site <site@example.com>',
			'path.views' => '/tests/Fixtures/templates',
			...$settings,
		]);
	}

	private function mailer(?Config $config = null): Mailer
	{
		$config ??= $this->mailConfig();
		$locales = new Locales();
		$locales->add('en', title: 'English');
		$locales->add('es', title: 'Español', fallback: 'en');
		$locales->catalog('app', self::root() . '/tests/Fixtures/lang');

		return new Mailer(
			$config,
			new Queue($this->db()),
			Context::console($this->db(), $config, new Container(), $this->factory(), $locales),
		);
	}

	private function transports(Config $config): Transports
	{
		return Transports::using($config, $this->logger, function (array $dsns, $dispatcher): RecordingTransport {
			$this->transport = new RecordingTransport($dispatcher);
			$this->transport->failure = $this->failure;

			return $this->transport;
		});
	}

	private function deliver(?Config $config = null): void
	{
		$transports = $this->transports($config ?? $this->mailConfig());
		$runner = new Runner(
			$this->db(),
			$this->logger,
			fn(string $class): object => new Delivery($transports, $this->logger),
		);

		$this->assertSame(1, $runner->run()['done']);
	}

	/** @return list<Email> */
	private function sent(): array
	{
		return array_map(
			static function (SentMessage $message): Email {
				$email = $message->getOriginalMessage();
				self::assertInstanceOf(Email::class, $email);

				return $email;
			},
			$this->transport->sent,
		);
	}

	/**
	 * @param array<Address> $addresses
	 * @return list<string>
	 */
	private static function addresses(array $addresses): array
	{
		return array_values(array_map(static fn(Address $address): string => $address->getAddress(), $addresses));
	}

	/** @param callable(): mixed $send */
	private function assertRefused(callable $send, string $message): void
	{
		try {
			$send();
			$this->fail('The mail was queued.');
		} catch (RuntimeException $e) {
			$this->assertStringContainsString($message, $e->getMessage());
		}
	}
}
