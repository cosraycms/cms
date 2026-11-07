<?php

declare(strict_types=1);

namespace Cosray\Value;

use Cosray\Assets\Asset;
use Cosray\LinkTarget;
use Cosray\Markup;
use Cosray\References\OwnerResolver;
use Override;

use function Cosray\escape;

/**
 * The link of a Link field for the owner's language. A target that is
 * gone or unsafe leaves no address, so templates print no dead link.
 */
class Link extends Value
{
	/** The address, escaped. */
	#[Override]
	public function __toString(): string
	{
		return escape($this->url());
	}

	/** The address the link leads to; empty without a link or when its target is gone. */
	public function url(): string
	{
		return $this->attributes()['href'] ?? '';
	}

	/**
	 * The attributes of the `<a>`: `href`, and `target` and `rel` when they apply.
	 *
	 * @return ?array<string, string>
	 */
	public function attributes(): ?array
	{
		$stored = $this->raw();

		return $stored === null ? null : LinkTarget::attributes($stored, new OwnerResolver($this->owner));
	}

	/**
	 * The content inside the link's `<a>`, or alone without a link. A string
	 * is escaped; a value or `Markup` is printed as it is.
	 */
	public function a(string|Value|Markup $content, string $class = ''): Markup
	{
		$html = is_string($content) ? escape($content) : (string) $content;
		$attributes = $this->attributes();

		if ($attributes === null) {
			return new Markup($html);
		}

		if ($class !== '') {
			$attributes['class'] = $class;
		}

		return new Markup(LinkTarget::anchor($attributes, $html));
	}

	/** The uid of the page the link leads to, if it leads to one. */
	public function node(): ?string
	{
		$node = $this->raw()['node'] ?? null;

		return is_string($node) && $node !== '' ? $node : null;
	}

	/** The file the link leads to, if it leads to one that still exists. */
	public function asset(): ?Asset
	{
		$uid = $this->raw()['asset'] ?? null;

		return is_string($uid) && $uid !== '' ? $this->owner->assets()->get($uid) : null;
	}

	#[Override]
	public function isset(): bool
	{
		return $this->raw() !== null;
	}

	/** @return ?array{href: string, target?: string, rel?: string} */
	#[Override]
	public function json(): ?array
	{
		/** @var ?array{href: string, target?: string, rel?: string} */
		return $this->attributes();
	}

	/**
	 * The stored link for the owner's language: `href`, `node` or `asset`,
	 * with `target` and `rel` when set.
	 *
	 * @return ?array<string, string>
	 */
	#[Override]
	public function raw(): ?array
	{
		$value = $this->value();

		if (!is_array($value)) {
			return null;
		}

		$stored = array_filter(
			array_intersect_key($value, array_flip(LinkTarget::KEYS)),
			static fn(mixed $part): bool => is_string($part) && $part !== '',
		);

		/** @var array<string, string> $stored */
		return array_intersect_key($stored, array_flip(['href', 'node', 'asset'])) === [] ? null : $stored;
	}
}
