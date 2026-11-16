<?php

declare(strict_types=1);

namespace Cosray\Text;

use Cosray\Actor;
use Cosray\Exception\RuntimeException;
use Cosray\Locales;
use Cosray\Node\Factory;
use Cosray\Node\Store;

/**
 * A node's working copy, opened by Edits to change its texts. Changes stay
 * in this object until save(); after it, open the node again for more.
 *
 * @api
 */
final class Edit
{
	/** @var array<mixed> */
	private array $content;

	private bool $changed = false;
	private bool $saved = false;

	/** @internal Opened by Edits::open(). */
	public function __construct(
		private readonly object $node,
		private readonly Texts $texts,
		private readonly Store $store,
		private readonly Locales $locales,
	) {
		$content = Factory::meta($node, 'content');
		$this->content = is_array($content) ? $content : [];
	}

	/**
	 * The texts of the working copy with the changes made so far.
	 *
	 * @return list<Text>
	 */
	public function texts(): array
	{
		return $this->texts->content($this->node, $this->content);
	}

	/** The text at a pointer, null once its field or row is gone. */
	public function text(string $pointer): ?Text
	{
		return array_find($this->texts(), static fn(Text $text): bool => $text->pointer === $pointer);
	}

	/**
	 * Replaces the code points from `$start` to `$end` of the text at the
	 * pointer. In rich text the replacement takes the marks of the run where
	 * the range starts.
	 */
	public function replace(string $pointer, int $start, int $end, string $replacement): void
	{
		if ($this->saved) {
			throw new RuntimeException('The edit is saved; open the node again to change it further');
		}

		$found = Pointer::get($this->content, $pointer);

		if (!$found['found'] || Unit::text($found['value']) === null) {
			throw new RuntimeException("No text at {$pointer}");
		}

		$this->content = Pointer::set(
			$this->content,
			$pointer,
			Unit::splice($found['value'], $start, $end, $replacement),
		);
		$this->changed = true;
	}

	/**
	 * Saves the changes where the panel saves an edit: into the working copy
	 * of a type with drafts, else into the main version. The node's history
	 * records `$cause` with the change. Without changes it saves nothing.
	 *
	 * @param array<string, mixed> $cause
	 */
	public function save(Actor $actor, array $cause = []): void
	{
		if (!$this->changed) {
			return;
		}

		/** @var array<string, mixed> $content */
		$content = $this->content;
		$this->store->amend($this->node, $content, $this->locales, $actor, $cause);
		$this->changed = false;
		$this->saved = true;
	}
}
