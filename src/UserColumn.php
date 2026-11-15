<?php

declare(strict_types=1);

namespace Cosray;

use Closure;
use Cosray\Node\Wrapper;
use Cosray\User\Avatar;

/**
 * A listing column of a user, made by Column::editor(), Column::creator()
 * or Column::user(): the avatar and the name, or either alone. As the first
 * column, which links the entry, it shows the name alone.
 */
final class UserColumn
{
	/** What the cells show: avatar and name, or either alone. */
	public private(set) string $look = 'both';
	public private(set) bool $bold = false;
	public private(set) bool $italic = false;

	/** @param 'editor'|'creator'|Closure(Wrapper): (User|Avatar|null) $user */
	public function __construct(
		public readonly string $title,
		private readonly string|Closure $user,
	) {}

	/** The avatar alone, the name its tooltip and accessible name. */
	public function avatar(): self
	{
		$this->look = 'avatar';

		return $this;
	}

	/** The name alone. */
	public function name(): self
	{
		$this->look = 'name';

		return $this;
	}

	public function bold(bool $bold = true): self
	{
		$this->bold = $bold;

		return $this;
	}

	public function italic(bool $italic = true): self
	{
		$this->italic = $italic;

		return $this;
	}

	public function kind(): string
	{
		return 'user';
	}

	/** @return array{value: ?Avatar, bold: bool, italic: bool} */
	public function get(Wrapper $node): array
	{
		return ['value' => $this->value($node), 'bold' => $this->bold, 'italic' => $this->italic];
	}

	private function value(Wrapper $node): ?Avatar
	{
		if ($this->user instanceof Closure) {
			$user = ($this->user)($node);

			return $user instanceof User ? $user->avatar() : $user;
		}

		$id = $node->meta->get("{$this->user}_usr");

		if (!is_numeric($id)) {
			return null;
		}

		$data = (array) $node->meta->get("{$this->user}_data", []);
		$avatar = new Avatar(
			(int) $id,
			is_string($data['name'] ?? null) ? $data['name'] : null,
			(string) $node->meta->get("{$this->user}_username", ''),
			(string) $node->meta->get("{$this->user}_email", ''),
		);

		return $avatar->label !== '' ? $avatar : null;
	}
}
