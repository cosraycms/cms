<?php

declare(strict_types=1);

namespace Cosray;

use Celema\Sire\Review;
use Celema\Sire\Shape;
use Cosray\References\Resolver;
use Cosray\Validation\Shapes;

/**
 * A stored link, in a richtext mark, on an image or in a Link field:
 * exactly one of `href`, `node` and `asset`, with an optional `target`
 * and `rel`.
 */
final class LinkTarget
{
	/** The keys a stored link may carry besides a richtext mark's `class`. */
	public const array KEYS = ['href', 'node', 'asset', 'target', 'rel'];

	public const string REL = 'noopener';

	/** What an editor can add to the `rel` of an address. */
	public const array RELS = ['nofollow', 'sponsored', 'noreferrer'];

	private const array SCHEMES = ['http', 'https', 'mailto', 'tel'];

	/** Whether an address is relative or uses a scheme that cannot run code in the page. */
	public static function safe(string $href): bool
	{
		// Browsers ignore spaces and control characters around a URL, and tabs and newlines inside it.
		$url = (string) preg_replace('/[\x00-\x20]+/', '', $href);

		if (preg_match('/^([a-z][a-z0-9+.-]*):/i', $url, $match) !== 1) {
			return true;
		}

		return in_array(strtolower($match[1]), self::SCHEMES, true);
	}

	/** @param array<mixed> $attrs */
	public static function valid(array $attrs): bool
	{
		$targets = array_filter(
			[$attrs['href'] ?? null, $attrs['node'] ?? null, $attrs['asset'] ?? null],
			static fn(mixed $target): bool => $target !== null && $target !== '',
		);

		return (
			count($targets) === 1
				&& (!is_string($attrs['href'] ?? null) || self::safe($attrs['href']))
				&& self::validRel($attrs['rel'] ?? null)
		);
	}

	public static function validRel(mixed $rel): bool
	{
		return $rel === null || is_string($rel) && array_diff(self::tokens($rel), self::RELS) === [];
	}

	/** The validation shape of one stored link. */
	public static function shape(): Shape
	{
		$shape = Shapes::create();

		foreach (self::KEYS as $key) {
			$shape->add($key, 'string')->optional()->nullable();
		}

		$shape->review(static function (Review $review): void {
			if (!self::valid($review->values())) {
				$review->addError('', __('link:invalid'));
			}
		});

		return $shape;
	}

	/**
	 * An `<a>` with the given attributes around content that is already HTML.
	 *
	 * @param array<string, string> $attributes
	 */
	public static function anchor(array $attributes, string $content): string
	{
		$html = '';

		foreach ($attributes as $name => $value) {
			$html .= sprintf(' %s="%s"', $name, escape($value));
		}

		return "<a{$html}>{$content}</a>";
	}

	/**
	 * The attributes of the `<a>`, or null without a target that can be linked.
	 *
	 * @param array<mixed> $attrs
	 * @return ?array<string, string>
	 */
	public static function attributes(array $attrs, Resolver $resolver, string $rel = self::REL): ?array
	{
		$href = self::href($attrs, $resolver);

		if ($href === null) {
			return null;
		}

		$html = ['href' => $href];

		foreach (['target', 'class'] as $key) {
			if (is_string($attrs[$key] ?? null) && $attrs[$key] !== '') {
				$html[$key] = $attrs[$key];
			}
		}

		// Links within the site carry no policy.
		if (is_string($attrs['href'] ?? null) && $attrs['href'] !== '') {
			$chosen = is_string($attrs['rel'] ?? null) ? self::tokens($attrs['rel']) : [];
			$html['rel'] = implode(' ', [$rel, ...array_intersect(self::RELS, $chosen)]);
		}

		return $html;
	}

	/**
	 * Why a link has no attributes; empty when it names no target at all.
	 *
	 * @param array<mixed> $attrs
	 */
	public static function problem(array $attrs): string
	{
		if (is_string($attrs['href'] ?? null) && $attrs['href'] !== '') {
			return 'unsafe link href';
		}

		if (is_string($attrs['node'] ?? null) && $attrs['node'] !== '') {
			return "unresolvable node link '{$attrs['node']}'";
		}

		if (is_string($attrs['asset'] ?? null) && $attrs['asset'] !== '') {
			return "unresolvable asset link '{$attrs['asset']}'";
		}

		return '';
	}

	/** @return list<string> */
	private static function tokens(string $rel): array
	{
		return preg_split('/\s+/', $rel, flags: PREG_SPLIT_NO_EMPTY) ?: [];
	}

	/** @param array<mixed> $attrs */
	private static function href(array $attrs, Resolver $resolver): ?string
	{
		if (is_string($attrs['href'] ?? null) && $attrs['href'] !== '') {
			return self::safe($attrs['href']) ? $attrs['href'] : null;
		}

		if (is_string($attrs['node'] ?? null) && $attrs['node'] !== '') {
			return $resolver->nodePath($attrs['node']);
		}

		if (is_string($attrs['asset'] ?? null) && $attrs['asset'] !== '') {
			return $resolver->asset($attrs['asset'])?->path();
		}

		return null;
	}
}
