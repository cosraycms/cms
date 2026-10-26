<?php

declare(strict_types=1);

namespace Cosray\Mail;

use Cosray\Exception\RuntimeException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Header\UnstructuredHeader;
use Symfony\Component\Mime\Part\DataPart;

/**
 * Turns a mail into the JSON payload of its delivery job and back. The
 * payload covers addresses, subject, text and HTML bodies, attachments,
 * embedded images, priority and plain text headers; a mail that uses
 * anything else is refused when it is queued, not when it is sent.
 *
 * @internal
 *
 * @phpstan-type Attachment array{name: ?string, type: string, body: string, inline: bool, cid: ?string}
 * @phpstan-type Data array{
 *     transport: ?string,
 *     from: list<string>,
 *     sender: ?string,
 *     returnPath: ?string,
 *     to: list<string>,
 *     cc: list<string>,
 *     bcc: list<string>,
 *     replyTo: list<string>,
 *     subject: ?string,
 *     text: ?string,
 *     html: ?string,
 *     priority: ?int,
 *     headers: list<array{string, string}>,
 *     attachments: list<Attachment>,
 * }
 */
final class Payload
{
	/** Bytes of attachments a queued mail may carry. */
	public const int MAX_ATTACHMENTS = 10 * 1024 * 1024;

	/** Headers the payload covers by their own keys, or that are set when sending. */
	private const array OWN_HEADERS = [
		'from',
		'sender',
		'return-path',
		'to',
		'cc',
		'bcc',
		'reply-to',
		'subject',
		'x-priority',
		'date',
		'message-id',
		'mime-version',
		'x-transport',
	];

	/** @return Data */
	public static function encode(Email $email, ?string $transport = null): array
	{
		return [
			'transport' => $transport,
			'from' => self::addresses($email->getFrom()),
			'sender' => $email->getSender()?->toString(),
			'returnPath' => $email->getReturnPath()?->toString(),
			'to' => self::addresses($email->getTo()),
			'cc' => self::addresses($email->getCc()),
			'bcc' => self::addresses($email->getBcc()),
			'replyTo' => self::addresses($email->getReplyTo()),
			'subject' => $email->getSubject(),
			'text' => self::body($email->getTextBody(), $email->getTextCharset()),
			'html' => self::body($email->getHtmlBody(), $email->getHtmlCharset()),
			'priority' => $email->getHeaders()->has('X-Priority') ? $email->getPriority() : null,
			'headers' => self::headers($email),
			'attachments' => self::attachments($email->getAttachments()),
		];
	}

	/**
	 * @param array<array-key, mixed> $payload
	 * @return array{Email, ?string} the mail and its transport's name
	 */
	public static function decode(array $payload): array
	{
		if (!self::valid($payload)) {
			throw new RuntimeException('A queued mail payload lacks keys or has values of the wrong type.');
		}

		$email = new Email();
		// Setting an empty list would add an empty header.
		$lists = [
			'from' => $email->from(...),
			'to' => $email->to(...),
			'cc' => $email->cc(...),
			'bcc' => $email->bcc(...),
			'replyTo' => $email->replyTo(...),
		];

		foreach ($lists as $key => $set) {
			if ($payload[$key] !== []) {
				$set(...$payload[$key]);
			}
		}

		if ($payload['priority'] !== null) {
			$email->priority($payload['priority']);
		}

		if ($payload['sender'] !== null) {
			$email->sender($payload['sender']);
		}

		if ($payload['returnPath'] !== null) {
			$email->returnPath($payload['returnPath']);
		}

		if ($payload['subject'] !== null) {
			$email->subject($payload['subject']);
		}

		if ($payload['text'] !== null) {
			$email->text($payload['text']);
		}

		if ($payload['html'] !== null) {
			$email->html($payload['html']);
		}

		foreach ($payload['headers'] as [$name, $value]) {
			$email->getHeaders()->addTextHeader($name, $value);
		}

		foreach ($payload['attachments'] as $attachment) {
			$body = base64_decode($attachment['body'], true);

			if ($body === false) {
				throw new RuntimeException('A queued attachment is not base64.');
			}

			$part = new DataPart($body, $attachment['name'], $attachment['type']);

			if ($attachment['inline']) {
				$part->asInline();
			}

			if ($attachment['cid'] !== null) {
				$part->setContentId($attachment['cid']);
			}

			$email->addPart($part);
		}

		return [$email, $payload['transport']];
	}

	/**
	 * @param array<array-key, mixed> $payload
	 * @phpstan-assert-if-true Data $payload
	 */
	private static function valid(array $payload): bool
	{
		$strings = static fn(mixed $list): bool => (
			is_array($list)
			&& array_is_list($list)
			&& array_all($list, static fn(mixed $item): bool => is_string($item))
		);
		$optional = static fn(string $key): bool => (
			array_key_exists($key, $payload)
			&& ($payload[$key] === null || is_string($payload[$key]))
		);

		return (
			array_all(['from', 'to', 'cc', 'bcc', 'replyTo'], static fn(string $key): bool => $strings(
				$payload[$key] ?? null,
			))
				&& array_all(['transport', 'sender', 'returnPath', 'subject', 'text', 'html'], $optional)
				&& array_key_exists('priority', $payload)
				&& ($payload['priority'] === null || is_int($payload['priority']))
				&& is_array($payload['headers'] ?? null)
				&& array_all(
					$payload['headers'],
					static fn(mixed $header): bool => $strings($header) && count($header) === 2,
				)
				&& is_array($payload['attachments'] ?? null)
				&& array_all($payload['attachments'], self::attachment(...))
		);
	}

	private static function attachment(mixed $part): bool
	{
		if (!is_array($part)) {
			return false;
		}

		$name = $part['name'] ?? null;
		$cid = $part['cid'] ?? null;

		return (
			($name === null || is_string($name))
				&& ($cid === null || is_string($cid))
				&& is_string($part['type'] ?? null)
				&& is_string($part['body'] ?? null)
				&& is_bool($part['inline'] ?? null)
		);
	}

	/**
	 * @param array<Address> $addresses
	 * @return list<string>
	 */
	private static function addresses(array $addresses): array
	{
		return array_values(array_map(static fn(Address $address): string => $address->toString(), $addresses));
	}

	/** @param resource|string|null $body */
	private static function body(mixed $body, ?string $charset): ?string
	{
		if ($body === null) {
			return null;
		}

		if ($charset !== null && strtolower($charset) !== 'utf-8') {
			throw new RuntimeException("A queued mail has UTF-8 bodies, not {$charset}.");
		}

		if (is_string($body)) {
			return $body;
		}

		// Symfony reads a seekable body from its start, wherever the stream stands.
		if (stream_get_meta_data($body)['seekable']) {
			rewind($body);
		}

		return (string) stream_get_contents($body);
	}

	/** @return list<array{string, string}> */
	private static function headers(Email $email): array
	{
		$headers = [];

		foreach ($email->getHeaders()->all() as $header) {
			if (in_array(strtolower($header->getName()), self::OWN_HEADERS, true)) {
				continue;
			}

			if (!$header instanceof UnstructuredHeader) {
				throw new RuntimeException("A queued mail takes text headers only, not {$header->getName()}.");
			}

			$headers[] = [$header->getName(), $header->getValue()];
		}

		return $headers;
	}

	/**
	 * @param array<DataPart> $parts
	 * @return list<Attachment>
	 */
	private static function attachments(array $parts): array
	{
		$attachments = [];
		$bytes = 0;

		foreach ($parts as $part) {
			$body = $part->getBody();
			$bytes += strlen($body);

			if ($bytes > self::MAX_ATTACHMENTS) {
				throw new RuntimeException(
					'A queued mail carries at most ' . ((self::MAX_ATTACHMENTS / 1024) / 1024) . ' MiB of attachments.',
				);
			}

			$attachments[] = [
				'name' => $part->getFilename(),
				'type' => $part->getContentType(),
				'body' => base64_encode($body),
				'inline' => $part->getDisposition() === 'inline',
				'cid' => $part->hasContentId() ? $part->getContentId() : null,
			];
		}

		return $attachments;
	}
}
