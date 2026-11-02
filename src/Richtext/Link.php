<?php

declare(strict_types=1);

namespace Cosray\Richtext;

/** A stored link, in a richtext mark or on an image: exactly one of `href`, `node` and `asset`. */
final class Link
{
	public const string REL = 'noopener noreferrer nofollow';
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

		return count($targets) === 1 && (!is_string($attrs['href'] ?? null) || self::safe($attrs['href']));
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
			$html['rel'] = $rel;
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
