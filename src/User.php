<?php

declare(strict_types=1);

namespace Cosray;

class User
{
	public readonly int $id;
	public readonly string $uid;
	public readonly string $type;
	public readonly string $username;
	public readonly string $email;
	public readonly string $password;
	/** @var list<string> */
	public readonly array $roles;
	public readonly bool $active;
	public readonly ?string $name;
	public readonly ?string $panelLocale;
	public readonly string $created;
	public readonly string $changed;
	public readonly ?string $deleted;
	public readonly ?string $expires;

	public function __construct(
		protected readonly array $data,
	) {
		$this->id = $data['usr'];
		$this->uid = $data['uid'];
		$this->type = $data['type'] ?? 'user';
		$this->username = $data['username'] ?? '';
		$this->email = $data['email'];
		$this->password = $data['password'];
		$this->roles = self::roleNames($data['roles'] ?? []);
		$this->active = $data['active'];
		$this->name = self::displayName($data['data'] ?? null);
		$this->panelLocale = $data['panel_locale'] ?? null;
		$this->created = $data['created'];
		$this->changed = $data['changed'];
		$this->deleted = $data['deleted'];
		$this->expires = $data['expires'] ?? null;
	}

	/**
	 * First and last word of the name, or of the username or the email's local
	 * part when there is none ("m.keller" → "MK"). A single word gives its
	 * first two letters.
	 */
	public function initials(): string
	{
		$source = $this->name ?? ($this->username !== '' ? $this->username : strstr($this->email, '@', true));
		$words = preg_split('/[\s._-]+/u', (string) $source, -1, PREG_SPLIT_NO_EMPTY) ?: [];

		$initials = match (count($words)) {
			0 => '',
			1 => mb_substr($words[0], 0, 2),
			default => mb_substr($words[0], 0, 1) . mb_substr($words[array_key_last($words)], 0, 1),
		};

		return mb_strtoupper($initials);
	}

	/** @return array<string, mixed> the `users.data` document */
	public function data(): array
	{
		$data = $this->data['data'] ?? [];

		if (is_string($data)) {
			$data = json_decode($data, true);
		}

		return is_array($data) ? $data : [];
	}

	/** @return array<string, array> the stored values of the fields a user model declares */
	public function content(): array
	{
		$content = $this->data()['content'] ?? [];

		return is_array($content) ? $content : [];
	}

	public function array(): array
	{
		$data = $this->data;
		unset($data['password']);

		return $data;
	}

	/** @return list<string> */
	private static function roleNames(mixed $roles): array
	{
		if (is_string($roles)) {
			$roles = json_decode($roles, true);
		}

		return is_array($roles) ? array_values(array_filter($roles, is_string(...))) : [];
	}

	private static function displayName(mixed $data): ?string
	{
		if (is_string($data)) {
			$data = json_decode($data, true);
		}

		$name = is_array($data) ? $data['name'] ?? null : null;

		return is_string($name) && trim($name) !== '' ? trim($name) : null;
	}
}
