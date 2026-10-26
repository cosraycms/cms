<?php

declare(strict_types=1);

namespace Cosray\Mail;

use Dom\Element;
use Dom\HTMLDocument;
use Dom\Node;
use Dom\Text;

/**
 * Reads what a mail needs from its rendered HTML: the subject from the
 * `<title>` element and a plain text version for the text part.
 *
 * @internal
 */
final class Html
{
	private const array BLOCKS = [
		'address',
		'article',
		'aside',
		'blockquote',
		'dd',
		'div',
		'dl',
		'dt',
		'figcaption',
		'figure',
		'footer',
		'h1',
		'h2',
		'h3',
		'h4',
		'h5',
		'h6',
		'header',
		'hr',
		'main',
		'nav',
		'ol',
		'p',
		'pre',
		'section',
		'table',
		'ul',
	];

	private const array HIDDEN = ['head', 'script', 'style', 'template', 'title'];

	public static function subject(string $html): string
	{
		return self::document($html)->title;
	}

	public static function text(string $html): string
	{
		$document = self::document($html);
		$text = self::children($document->body ?? $document, false);
		$lines = array_map(
			static fn(string $line): string => rtrim($line),
			explode("\n", $text),
		);

		return trim((string) preg_replace("/\n{3,}/", "\n\n", implode("\n", $lines)));
	}

	private static function document(string $html): HTMLDocument
	{
		return HTMLDocument::createFromString($html, LIBXML_NOERROR, 'UTF-8');
	}

	private static function children(Node $node, bool $pre): string
	{
		$text = '';

		foreach ($node->childNodes as $child) {
			$text .= self::node($child, $pre);
		}

		return $text;
	}

	private static function node(Node $node, bool $pre): string
	{
		if ($node instanceof Text) {
			return $pre ? $node->data : (string) preg_replace('/\s+/u', ' ', $node->data);
		}

		if (!$node instanceof Element) {
			return '';
		}

		$name = strtolower($node->localName);

		if (in_array($name, self::HIDDEN, true)) {
			return '';
		}

		$inner = self::children($node, $pre || $name === 'pre');

		return match (true) {
			$name === 'br' => "\n",
			$name === 'img' => (string) $node->getAttribute('alt'),
			$name === 'a' => self::link($inner, (string) $node->getAttribute('href')),
			$name === 'li' => "\n- " . trim($inner),
			$name === 'tr' => "\n" . trim($inner),
			$name === 'td' || $name === 'th' => trim($inner) . ' ',
			in_array($name, self::BLOCKS, true) => "\n\n" . ($name === 'pre' ? $inner : trim($inner)) . "\n\n",
			default => $inner,
		};
	}

	private static function link(string $text, string $href): string
	{
		$label = trim($text);
		$target = str_starts_with($href, 'mailto:') ? substr($href, 7) : $href;

		if ($target === '' || str_starts_with($target, '#') || $target === $label) {
			return $text;
		}

		return $label === '' ? $target : "{$label} ({$target})";
	}
}
