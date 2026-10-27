<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Celema\Core\App;
use Cosray\Config;
use Cosray\Mail\Transports;
use Cosray\Tests\End2EndTestCase;
use Cosray\Tests\Fixtures\Mail\RecordingTransport;
use Cosray\User\PasswordResetMail;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * @internal
 *
 * @coversNothing
 */
final class PanelPasswordResetTest extends End2EndTestCase
{
	private const array SETTINGS = [
		'app.url' => 'https://example.org/',
		'mail.transports' => ['default' => 'null://null'],
		'mail.from' => 'site@example.com',
	];

	private ?RecordingTransport $transport = null;

	protected function setUp(): void
	{
		parent::setUp();

		$this->db()->execute('DELETE FROM cms.jobs')->run();
		$this->app = $this->recordingApp(self::SETTINGS);
	}

	public function testWithoutMailOrAppUrlThereIsNoReset(): void
	{
		$this->app = $this->createApp(['mail.transports' => ['default' => 'null://null']]);

		$this->assertResponseStatus(404, $this->makeRequest('GET', '/panel/password'));
		$this->assertStringNotContainsString('/panel/password', $this->html('GET', '/panel/login'));
	}

	public function testTheLoginPageLinksToTheResetForm(): void
	{
		$this->assertStringContainsString('href="/panel/password"', $this->html('GET', '/panel/login'));
		$this->assertStringContainsString('action="/panel/password"', $this->html('GET', '/panel/password'));
	}

	public function testALinkLetsTheUserChooseANewPasswordOnce(): void
	{
		$this->createTestUser([
			'username' => 'forgetful',
			'email' => 'forgetful@example.com',
			'data' => ['name' => 'Ann'],
		]);

		$sent = $this->html('POST', '/panel/password', ['login' => 'forgetful@example.com']);
		$this->assertStringContainsString('a link to choose a new password is on its way', $sent);

		$mail = $this->mails()[0];
		$this->assertSame(['"Ann" <forgetful@example.com>'], $mail['to']);
		$this->assertSame('A new password for cosray', $mail['subject']);
		$this->assertStringContainsString('Hello Ann,', (string) $mail['html']);
		$this->assertSame(1, preg_match(
			'#https://example\.org/panel/password/([a-f0-9]{64})#',
			(string) $mail['html'],
			$link,
		));
		$path = '/panel/password/' . $link[1];

		$this->assertStringContainsString('action="' . $path . '"', $this->html('GET', $path));
		$this->assertStringContainsString(
			'This password is too short. Use at least 12 characters.',
			$this->html('POST', $path, ['password' => 'too short', 'password_repeat' => 'too short']),
		);
		$this->assertStringContainsString(
			'This password is too long. Use at most 512 characters.',
			$this->html('POST', $path, ['password' => str_repeat('a', 513), 'password_repeat' => str_repeat('a', 513)]),
		);
		$this->assertStringContainsString(
			'The passwords do not match.',
			$this->html('POST', $path, [
				'password' => 'a long new password',
				'password_repeat' => 'a long new passwort',
			]),
		);

		$done = $this->html('POST', $path, [
			'password' => 'a long new password',
			'password_repeat' => 'a long new password',
		]);
		$this->assertStringContainsString('Your password has been changed.', $done);
		$this->assertContains(['notice', 'Password of forgetful reset from 192.0.2.7'], $this->logger->records);

		$this->assertResponseStatus(303, $this->signIn('forgetful', 'a long new password'));
		$this->assertResponseOk($this->signIn('forgetful', 'password'));
		$this->assertStringContainsString('This link does not work', $this->html('GET', $path));
	}

	public function testTheAnswerIsTheSameForLoginsWithoutAMail(): void
	{
		$this->createTestUser(['username' => 'outsider', 'role' => 'system']);
		$this->createTestUser(['username' => 'recent']);

		$answers = array_map(
			fn(string $login): string => $this->message($this->html('POST', '/panel/password', ['login' => $login])),
			['nobody@example.com', 'outsider', 'recent', 'recent'],
		);

		$this->assertCount(1, array_unique($answers));
		// Only the first request for `recent` sends a mail; the second comes too soon.
		$this->assertCount(1, $this->mails());
	}

	public function testARequestDoesTheSameWorkForEveryLogin(): void
	{
		$this->app = $this->recordingApp([...self::SETTINGS, 'queue.after_response' => false]);
		$this->createTestUser(['username' => 'known']);

		$this->html('POST', '/panel/password', ['login' => 'known']);
		$this->html('POST', '/panel/password', ['login' => 'unknown']);

		$jobs = $this->db()->execute('SELECT job, queue, payload::text AS payload FROM cms.jobs ORDER BY id')->all();
		$this->assertSame([PasswordResetMail::class, PasswordResetMail::class], array_column($jobs, 'job'));
		$this->assertSame(
			array_keys(json_decode($jobs[0]['payload'], true)),
			array_keys(json_decode($jobs[1]['payload'], true)),
		);
		$this->assertSame(0, $this->db()->execute('SELECT count(*) AS n FROM cms.password_resets')->one()['n']);
	}

	public function testAFailedMailLeavesNoLinkThatWouldHoldUpTheRetry(): void
	{
		// Without a sender, the mail cannot be queued.
		$this->app = $this->recordingApp([...self::SETTINGS, 'mail.from' => null]);
		$this->createTestUser(['username' => 'unlucky', 'email' => 'unlucky@example.com']);

		$this->html('POST', '/panel/password', ['login' => 'unlucky']);

		$this->assertSame(0, $this->db()->execute('SELECT count(*) AS n FROM cms.password_resets')->one()['n']);
		$this->assertSame([], $this->mails());

		$this->app = $this->recordingApp(self::SETTINGS);
		$this->db()->execute('UPDATE cms.jobs SET due = now()')->run();
		$this->makeRequest('GET', '/panel/login');

		$this->assertSame([['"Test User" <unlucky@example.com>']], array_column($this->mails(), 'to'));
	}

	public function testManyRequestsFromOneAddressAreRefusedForAWhile(): void
	{
		$this->app = $this->recordingApp([...self::SETTINGS, 'queue.after_response' => false]);

		for ($i = 0; $i < 5; $i++) {
			$this->html('POST', '/panel/password', ['login' => "someone-{$i}"]);
		}

		$refused = $this->makeRequest('POST', '/panel/password', [
			'body' => ['login' => 'someone-else'],
			'server' => ['REMOTE_ADDR' => '192.0.2.7'],
		]);
		$this->assertResponseStatus(429, $refused);
		$this->assertSame('900', $refused->getHeaderLine('Retry-After'));
		$this->assertStringContainsString('Too many attempts', $this->getHtmlResponse($refused));
		$this->assertSame(5, $this->db()->execute('SELECT count(*) AS n FROM cms.jobs')->one()['n']);
	}

	public function testTheMailIsInThePanelLanguageTheUserChose(): void
	{
		$id = $this->createTestUser(['username' => 'german', 'data' => ['name' => 'Anna']]);
		$this->db()->execute("UPDATE cms.users SET panel_locale = 'de' WHERE usr = :usr", ['usr' => $id])->run();

		$this->html('POST', '/panel/password', ['login' => 'german']);

		$this->assertSame('Ein neues Passwort für cosray', $this->mails()[0]['subject']);
		$this->assertStringContainsString('Neues Passwort wählen', (string) $this->mails()[0]['text']);
	}

	public function testWithoutAChoiceTheMailIsInTheLanguageOfTheRequest(): void
	{
		$this->createTestUser(['username' => 'browser', 'data' => ['name' => 'Anna']]);

		$this->html('POST', '/panel/password', ['login' => 'browser'], ['Accept-Language' => 'de-DE,de;q=0.9']);

		$this->assertSame('Ein neues Passwort für cosray', $this->mails()[0]['subject']);
	}

	public function testAnExpiredLinkDoesNotWork(): void
	{
		$this->createTestUser(['username' => 'late']);
		$this->html('POST', '/panel/password', ['login' => 'late']);
		preg_match('#/panel/password/[a-f0-9]{64}#', (string) $this->mails()[0]['html'], $link);
		$this->db()->execute("UPDATE cms.password_resets SET expires = now() - interval '1 second'")->run();

		$this->assertStringContainsString('This link does not work', $this->html('GET', $link[0]));
		$this->assertStringContainsString(
			'This link does not work',
			$this->html('POST', $link[0], [
				'password' => 'a long new password',
				'password_repeat' => 'a long new password',
			]),
		);
		$this->assertStringContainsString('Please enter your username or email address.', $this->html(
			'POST',
			'/panel/password',
			['login' => ' '],
		));
	}

	/**
	 * @param array<string, mixed> $settings
	 */
	private function recordingApp(array $settings): App
	{
		$app = $this->createApp($settings);
		$app->container()->add(Transports::class, new Transports(
			$app->container()->get(Config::class),
			$this->logger,
			fn(array $dsns, EventDispatcher $events) => $this->transport = new RecordingTransport($events),
		));

		return $app;
	}

	/**
	 * @param array<string, string> $form
	 * @param array<string, string> $headers
	 */
	private function html(string $method, string $uri, array $form = [], array $headers = []): string
	{
		$response = $this->makeRequest($method, $uri, [
			'body' => $form,
			'headers' => $headers,
			'server' => ['REMOTE_ADDR' => '192.0.2.7'],
		]);
		$this->assertResponseOk($response);

		return $this->getHtmlResponse($response);
	}

	private function signIn(string $login, #[\SensitiveParameter] string $password): ResponseInterface
	{
		return $this->makeRequest('POST', '/panel/login', [
			'body' => ['login' => $login, 'password' => $password, 'rememberme' => false, 'next' => '/panel'],
		]);
	}

	/**
	 * The mails sent through the transport, by the jobs that requests run
	 * once their response is out.
	 *
	 * @return list<array{to: list<string>, subject: ?string, html: ?string, text: ?string}>
	 */
	private function mails(): array
	{
		$mails = [];

		foreach ($this->transport->sent ?? [] as $message) {
			$email = $message->getOriginalMessage();
			$this->assertInstanceOf(Email::class, $email);
			$mails[] = [
				'to' => array_map(static fn(Address $address): string => $address->toString(), $email->getTo()),
				'subject' => $email->getSubject(),
				'html' => is_string($email->getHtmlBody()) ? $email->getHtmlBody() : null,
				'text' => is_string($email->getTextBody()) ? $email->getTextBody() : null,
			];
		}

		return $mails;
	}

	private function message(string $html): string
	{
		preg_match('#<p class="intro" role="status">(.*?)</p>#s', $html, $match);

		return trim($match[1] ?? '');
	}
}
