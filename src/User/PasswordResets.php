<?php

declare(strict_types=1);

namespace Cosray\User;

use Celema\Quma\Database;
use Cosray\Actor;
use Cosray\Config;
use Cosray\User;
use Cosray\Users;

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
	) {}

	/** Whether links can be sent: they need a mail transport and `app.url`. */
	public function available(): bool
	{
		return $this->config->mail->transports !== [] && $this->config->app->url !== null;
	}

	/** A new link's token, or null while the user's last link is younger than the interval. */
	public function issue(User $user): ?string
	{
		/** @var array{recent: bool} $recent */
		$recent = $this->db->passwordResets->recent(['usr' => $user->id, 'interval' => self::INTERVAL])->one();

		if ($recent['recent']) {
			return null;
		}

		$this->db->passwordResets->expired()->run();
		$token = bin2hex(random_bytes(32));
		$this->db->passwordResets->create([
			'token' => hash('sha256', $token),
			'usr' => $user->id,
			'lifetime' => self::LIFETIME,
		])->run();

		return $token;
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
	 * Sets the password of the link's user, which also ends their sessions,
	 * and uses up the link. Returns the user, or null if the link does not work.
	 */
	public function complete(#[\SensitiveParameter] string $token, #[\SensitiveParameter] string $password): ?User
	{
		$user = $this->user($token);

		if ($user === null) {
			return null;
		}

		$this->users->setPassword($user, $password, Actor::system());
		$this->db->passwordResets->clear(['usr' => $user->id])->run();

		return $user;
	}

	/** The absolute URL of a link. */
	public function url(#[\SensitiveParameter] string $token): string
	{
		return $this->config->app->url . rtrim($this->config->panel->path, '/') . '/password/' . $token;
	}
}
