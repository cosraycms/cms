<?php

declare(strict_types=1);

namespace Cosray\User;

use Celema\Quma\Database;
use Cosray\Actor;
use Cosray\Config;
use Cosray\Queue;
use Cosray\User;
use Cosray\Users;
use Cosray\Util\Transaction;

/**
 * Links that let panel users who forgot their password choose a new one.
 * A link holds a random token, of which only the sha256 is stored; it
 * works once and for an hour, and a newer link replaces it.
 */
final class PasswordResets
{
	/** Seconds a link works. */
	public const int LIFETIME = 3600;

	/** Seconds before a user gets another link. */
	public const int INTERVAL = 300;

	public function __construct(
		private readonly Database $db,
		private readonly Users $users,
		private readonly Config $config,
		private readonly Queue $queue,
	) {}

	/** Whether links can be sent: they need a mail transport and `app.url`. */
	public function available(): bool
	{
		return $this->config->mail->transports !== [] && $this->config->app->url !== null;
	}

	/**
	 * Queues sending a link to the user with this login, if there is one;
	 * the mail is in the user's panel language, else in the given one.
	 */
	public function request(string $login, string $ip, ?string $locale): void
	{
		$this->queue->push(
			PasswordResetMail::class,
			['login' => $login, 'ip' => $ip, 'locale' => $locale],
			queue: 'mail',
		);
	}

	/**
	 * A new link's token, or null while the user's last link is younger than
	 * the interval. The upsert itself checks the interval, so concurrent
	 * requests cannot both replace a link.
	 */
	public function issue(User $user): ?string
	{
		$this->db->passwordResets->expired()->run();
		$token = bin2hex(random_bytes(32));
		$issued = $this->db
			->passwordResets
			->create([
				'token' => hash('sha256', $token),
				'usr' => $user->id,
				'lifetime' => self::LIFETIME,
				'interval' => self::INTERVAL,
			])
			->first();

		return $issued === null ? null : $token;
	}

	/** The active user a working link was issued for. */
	public function user(#[\SensitiveParameter] string $token): ?User
	{
		/** @var ?array{usr: int} $row */
		$row = $this->db->passwordResets->user(['token' => hash('sha256', $token)])->first();
		$user = $row === null ? null : $this->users->byId($row['usr']);

		return $user !== null && $user->active ? $user : null;
	}

	/**
	 * Uses up the link and sets the password of its user, which also ends
	 * their sessions, all or nothing. Returns the user, or null if the link
	 * does not work; of concurrent requests with one link, only the first
	 * gets the user.
	 */
	public function complete(#[\SensitiveParameter] string $token, #[\SensitiveParameter] string $password): ?User
	{
		return Transaction::run($this->db, function () use ($token, $password): ?User {
			/** @var ?array{usr: int} $row */
			$row = $this->db->passwordResets->consume(['token' => hash('sha256', $token)])->first();
			$user = $row === null ? null : $this->users->byId($row['usr']);

			if ($user === null || !$user->active) {
				return null;
			}

			$this->users->setPassword($user, $password, Actor::system());

			return $user;
		});
	}

	/** The absolute URL of a link. */
	public function url(#[\SensitiveParameter] string $token): string
	{
		return $this->config->app->url . rtrim($this->config->panel->path, '/') . '/password/' . $token;
	}
}
