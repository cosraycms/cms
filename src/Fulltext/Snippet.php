<?php

declare(strict_types=1);

namespace Cosray\Fulltext;

final readonly class Snippet
{
	/** @var list<array{text: string, match: bool}> */
	public array $segments;

	public function __construct(string $headline)
	{
		$segments = [];
		$match = false;
		foreach (preg_split(
			'/([\x01\x02])/',
			$headline,
			flags: PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY,
		) as $part) {
			if ($part === "\x01" || $part === "\x02") {
				$match = $part === "\x01";
			} else {
				$segments[] = ['text' => $part, 'match' => $match];
			}
		}
		$this->segments = $segments;
	}

	public function text(): string
	{
		return implode('', array_column($this->segments, 'text'));
	}

	public function html(): string
	{
		$html = '';
		foreach ($this->segments as $segment) {
			$text = htmlspecialchars($segment['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
			$html .= $segment['match'] ? '<mark>' . $text . '</mark>' : $text;
		}
		return $html;
	}
}
