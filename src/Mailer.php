<?php

declare(strict_types=1);

namespace Cosray;

use Celema\Boiler\Engine;
use Cosray\Exception\RuntimeException;
use Cosray\Mail\Delivery;
use Cosray\Mail\Html;
use Cosray\Mail\Payload;
use Cosray\View\Boiler\Renderer;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Exception\LogicException;

/**
 * Sends mail through the job queue with Symfony Mailer. `send()` renders
 * and checks the mail right away and queues it; delivery and its retries
 * happen later, so a send that returns has not reached anyone yet. Inside
 * a transaction, the mail is queued only if the transaction commits.
 *
 *     $mailer->send('contact', to: $recipients, data: $form, replyTo: $form['email']);
 *     $mailer->via('newsletter')->send($email);
 *
 * Templates are Boiler templates in the `mail.views` directories, then in
 * Cosray's own: the HTML body, whose `<title>` is the subject, and an
 * optional `<name>.txt` for the text body, otherwise derived from the HTML.
 *
 * @api
 */
final class Mailer
{
	/** Cosray's own templates, after the application's, which can replace them. */
	private const string VIEWS = __DIR__ . '/../resources/mail';

	private ?string $transport = null;

	public function __construct(
		private readonly Config $config,
		private readonly Queue $queue,
		private readonly Context $context,
	) {}

	/** A mailer that sends through the named transport instead of the default one. */
	public function via(string $transport): self
	{
		if (!array_key_exists($transport, $this->config->mail->transports)) {
			throw new RuntimeException("There is no mail transport named {$transport}.");
		}

		$mailer = clone $this;
		$mailer->transport = $transport;

		return $mailer;
	}

	/**
	 * Renders a template into a mail without sending it, to add what
	 * `send()` does not cover, like attachments.
	 *
	 * @param non-empty-string $template
	 * @param array<string, mixed> $data
	 * @param ?string $locale one of the site's locales; the current one if null
	 */
	public function compose(string $template, array $data = [], ?string $locale = null): Email
	{
		$render = function () use ($template, $data): Email {
			// Boiler refuses missing directories, and a site without mails of its own has none.
			$views = array_values(array_filter([...$this->config->mail->views, self::VIEWS], is_dir(...)));
			$engine = Engine::create($views, trusted: Renderer::TRUSTED);
			$html = $engine->render($template, $data);
			$text = $engine->exists("{$template}.txt")
				? $engine->renderUnescaped("{$template}.txt", $data)
				: Html::text($html);

			return new Email()
				->subject(Html::subject($html))
				->html($html)
				->text($text);
		};

		if ($locale === null) {
			return $render();
		}

		return $this->context->withLocale($this->context->locales()->get($locale), $render);
	}

	/**
	 * The mails queued and not yet delivered, whatever their transport, in
	 * the order they were sent, as their delivery will hand them over: a mail
	 * for a named transport carries it in `X-Transport`. Tests read what the
	 * code under test sent with it.
	 *
	 * @return list<Email>
	 */
	public function queued(): array
	{
		return array_map(static function (array $payload): Email {
			[$email, $transport] = Payload::decode($payload);

			if ($transport !== null) {
				$email->getHeaders()->addTextHeader('X-Transport', $transport);
			}

			return $email;
		}, $this->queue->pending(Delivery::class));
	}

	/**
	 * Queues a mail: a rendered template, or a mail built by hand or with
	 * `compose()`. Addresses add to those the mail has; a mail without a
	 * sender gets `mail.from`.
	 *
	 * @param non-empty-string|Email $mail a template name or a mail
	 * @param Address|string|list<Address|string> $to
	 * @param array<string, mixed> $data for the template
	 * @param Address|string|list<Address|string> $replyTo
	 * @param Address|string|list<Address|string> $cc
	 * @param Address|string|list<Address|string> $bcc
	 * @param ?string $subject replaces the template's
	 * @param ?string $locale for the template, one of the site's locales
	 * @param int $delay seconds before the mail is sent
	 * @return int the id of the delivery job
	 */
	public function send(
		string|Email $mail,
		Address|string|array $to = [],
		array $data = [],
		Address|string|array $replyTo = [],
		Address|string|array $cc = [],
		Address|string|array $bcc = [],
		?string $subject = null,
		?string $locale = null,
		int $delay = 0,
	): int {
		if ($this->config->mail->transports === []) {
			throw new RuntimeException('No mail transport is configured: set MAILER_DSN or `mail.transports`.');
		}

		if ($mail instanceof Email && ($data !== [] || $locale !== null)) {
			throw new RuntimeException('Data and locale are for templates; render one with compose() first.');
		}

		$email = $mail instanceof Email ? $mail : $this->compose($mail, $data, $locale);
		$this->address($email, $to, $replyTo, $cc, $bcc);

		if ($subject !== null) {
			$email->subject($subject);
		}

		if (trim((string) $email->getSubject()) === '') {
			throw new RuntimeException('A mail needs a subject: give its template a <title> or pass one.');
		}

		try {
			$email->ensureValidity();
		} catch (LogicException $e) {
			throw new RuntimeException("The mail cannot be sent: {$e->getMessage()}", previous: $e);
		}

		return $this->queue->push(Delivery::class, Payload::encode($email, $this->transport), $delay, 'mail');
	}

	/**
	 * @param Address|string|list<Address|string> $to
	 * @param Address|string|list<Address|string> $replyTo
	 * @param Address|string|list<Address|string> $cc
	 * @param Address|string|list<Address|string> $bcc
	 */
	private function address(
		Email $email,
		Address|string|array $to,
		Address|string|array $replyTo,
		Address|string|array $cc,
		Address|string|array $bcc,
	): void {
		$lists = [
			[$email->addTo(...), $to],
			[$email->addReplyTo(...), $replyTo],
			[$email->addCc(...), $cc],
			[$email->addBcc(...), $bcc],
		];

		foreach ($lists as [$add, $addresses]) {
			// Adding none would add an empty header, which passes the check for recipients.
			if ($addresses !== []) {
				$add(...self::list($addresses));
			}
		}

		if ($email->getFrom() === [] && $email->getSender() === null) {
			$email->from(
				$this->config->mail->from ?? throw new RuntimeException(
					'A mail needs a sender: set MAILER_FROM or `mail.from`, or pass one.',
				),
			);
		}
	}

	/**
	 * @param Address|string|list<Address|string> $addresses
	 * @return list<Address|string>
	 */
	private static function list(Address|string|array $addresses): array
	{
		return is_array($addresses) ? $addresses : [$addresses];
	}
}
