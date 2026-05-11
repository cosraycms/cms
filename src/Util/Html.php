<?php

declare(strict_types=1);

namespace Celemas\Cms\Util;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

class Html
{
	public static function excerpt(
		string $html,
		$limit = 30,
		$allowedtags = '',
		$ellipsis = '…',
	) {
		$result = strip_tags($html, $allowedtags);

		if (str_word_count($result, 0) > $limit) {
			$words = str_word_count($result, 2);
			$pos = array_keys($words);
			$result = substr($result, 0, $pos[$limit]) . $ellipsis;
		}

		return self::balanceTags($result);
	}

	/**
	 * Idea from this answer: https://stackoverflow.com/a/1725345.
	 *
	 * TODO: handle <br> etc.
	 */
	public static function balanceTags(string $text): string
	{
		// Find all opened tags in the front string
		$tags = [];
		preg_match_all(
			'/<\\s*([a-z][\\w]*)(?: +\\w*\\s*=\\s*"[\\s\w?\\/%&=#^$_:()*^-]+")*\\s*>/i',
			$text,
			$tags,
			PREG_OFFSET_CAPTURE,
		);
		array_shift($tags); // get rid of the complete match from preg_match_all

		// Check if the opened arrays have been closed in the front string
		$unclosed = [];

		foreach ($tags[0] as $t) {
			[$tag, $pos] = $t;

			if (strpos($text, '</' . $tag, $pos) === false) {
				$unclosed[] = $tag;
			}
		}

		foreach (array_reverse($unclosed) as $tag) {
			$text .= '</' . $tag . '>';
		}

		return $text;
	}

	public static function sanitize(
		string $html,
		?HtmlSanitizerConfig $config = null,
		bool $removeEmptyLines = true,
	): string {
		$config = $config ?: new HtmlSanitizerConfig()
			// Allow "safe" elements and attributes. All scripts will be removed
			// as well as other dangerous behaviors like CSS injection
			->allowStaticElements()
			->allowLinkSchemes(['http', 'https', 'mailto'])
			->allowRelativeLinks();
		$sanitizer = new HtmlSanitizer($config);
		$result = $sanitizer->sanitize($html);

		// also remove empty lines
		return $removeEmptyLines
			? preg_replace("/(^[\r\n]*|[\r\n]+)[\\s\t]*[\r\n]+/", PHP_EOL, $result)
			: $result;
	}
}
