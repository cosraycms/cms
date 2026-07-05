<?php

declare(strict_types=1);

namespace Cosray\Richtext;

use function Cosray\escape;

/**
 * Renders a richtext document to HTML. Reader-tolerant: unknown node
 * or mark types and dangling references degrade instead of crashing;
 * `notices()` reports what was skipped.
 */
final class Renderer
{
	/** @var list<string> */
	private array $notices = [];

	public function __construct(
		private readonly Resolver $resolver,
		private readonly string $imageSize = 'block',
		private readonly string $linkRel = 'noopener noreferrer nofollow',
	) {}

	public function render(mixed $doc): string
	{
		$this->notices = [];

		if (!is_array($doc) || ($doc['type'] ?? null) !== 'doc') {
			return '';
		}

		return $this->children($doc['content'] ?? null);
	}

	/** @return list<string> What the last render skipped or could not resolve. */
	public function notices(): array
	{
		return $this->notices;
	}

	private function children(mixed $content): string
	{
		if (!is_array($content)) {
			return '';
		}

		$html = '';

		foreach ($content as $child) {
			if (is_array($child)) {
				$html .= $this->node($child);
			}
		}

		return $html;
	}

	private function node(array $node): string
	{
		$type = $node['type'] ?? null;

		if (!is_string($type)) {
			return '';
		}

		return match ($type) {
			'paragraph' => $this->tag(
				'p',
				$this->blockAttrs($node),
				$this->children($node['content'] ?? null),
			),
			'heading' => $this->heading($node),
			'bulletList' => $this->tag('ul', [], $this->children($node['content'] ?? null)),
			'orderedList' => $this->orderedList($node),
			'listItem' => $this->tag('li', [], $this->children($node['content'] ?? null)),
			'blockquote' => $this->tag('blockquote', [], $this->children($node['content'] ?? null)),
			'codeBlock' => '<pre><code>' . $this->children($node['content'] ?? null) . '</code></pre>',
			'horizontalRule' => $this->horizontalRule($node),
			'hardBreak' => '<br>',
			'text' => $this->text($node),
			'image' => $this->image($node),
			default => $this->skip("unknown node type '{$type}'"),
		};
	}

	private function heading(array $node): string
	{
		$level = $node['attrs']['level'] ?? 1;
		$level = is_int($level) && $level >= 1 && $level <= 6 ? $level : 1;

		return $this->tag(
			"h{$level}",
			$this->alignAttrs($node),
			$this->children($node['content'] ?? null),
		);
	}

	private function orderedList(array $node): string
	{
		$start = $node['attrs']['start'] ?? 1;
		$attrs = is_int($start) && $start !== 1 ? ['start' => (string) $start] : [];

		return $this->tag('ol', $attrs, $this->children($node['content'] ?? null));
	}

	private function horizontalRule(array $node): string
	{
		$class = $node['attrs']['class'] ?? null;

		return is_string($class) && $class !== ''
			? '<hr' . $this->attrString(['class' => $class]) . '>'
			: '<hr>';
	}

	/** @return array<string, string> */
	private function blockAttrs(array $node): array
	{
		$attrs = [];
		$class = $node['attrs']['class'] ?? null;

		if (is_string($class) && $class !== '' && $class !== 'default') {
			$attrs['class'] = $class;
		}

		return array_merge($attrs, $this->alignAttrs($node));
	}

	/** @return array<string, string> */
	private function alignAttrs(array $node): array
	{
		$align = $node['attrs']['align'] ?? null;

		if (is_string($align) && in_array($align, Spec::ALIGNMENTS, true)) {
			return ['style' => "text-align: {$align}"];
		}

		return [];
	}

	private function text(array $node): string
	{
		$text = $node['text'] ?? null;

		if (!is_string($text) || $text === '') {
			return '';
		}

		$html = escape($text);
		$marks = [];

		foreach (is_array($node['marks'] ?? null) ? $node['marks'] : [] as $mark) {
			if (is_array($mark) && is_string($mark['type'] ?? null)) {
				$marks[$mark['type']] = $mark;
			}
		}

		foreach (array_reverse(Spec::MARK_ORDER) as $type) {
			if (isset($marks[$type])) {
				$html = $this->mark($marks[$type], $html);
				unset($marks[$type]);
			}
		}

		foreach (array_keys($marks) as $unknown) {
			$this->skip("unknown mark type '{$unknown}'");
		}

		return $html;
	}

	private function mark(array $mark, string $inner): string
	{
		return match ($mark['type']) {
			'bold' => "<strong>{$inner}</strong>",
			'italic' => "<em>{$inner}</em>",
			'underline' => "<u>{$inner}</u>",
			'strike' => "<s>{$inner}</s>",
			'code' => "<code>{$inner}</code>",
			'subscript' => "<sub>{$inner}</sub>",
			'superscript' => "<sup>{$inner}</sup>",
			'style' => $this->styleMark($mark, $inner),
			'link' => $this->link($mark, $inner),
			default => $inner,
		};
	}

	private function styleMark(array $mark, string $inner): string
	{
		$class = $mark['attrs']['class'] ?? null;

		if (!is_string($class) || $class === '') {
			return $inner;
		}

		return '<span' . $this->attrString(['class' => $class]) . ">{$inner}</span>";
	}

	private function link(array $mark, string $inner): string
	{
		$attrs = is_array($mark['attrs'] ?? null) ? $mark['attrs'] : [];
		$href = null;
		$rel = null;

		if (is_string($attrs['href'] ?? null) && $attrs['href'] !== '') {
			$href = $attrs['href'];
			$rel = $this->linkRel;
		} elseif (is_string($attrs['node'] ?? null) && $attrs['node'] !== '') {
			$href = $this->resolver->nodePath($attrs['node']);

			if ($href === null) {
				return $this->skip("unresolvable node link '{$attrs['node']}'") . $inner;
			}
		} elseif (is_string($attrs['asset'] ?? null) && $attrs['asset'] !== '') {
			$asset = $this->resolver->asset($attrs['asset']);

			if ($asset === null) {
				return $this->skip("unresolvable asset link '{$attrs['asset']}'") . $inner;
			}

			$href = $asset->path();
		} else {
			return $inner;
		}

		$html = ['href' => $href];

		if (is_string($attrs['target'] ?? null) && $attrs['target'] !== '') {
			$html['target'] = $attrs['target'];
		}

		if (is_string($attrs['class'] ?? null) && $attrs['class'] !== '') {
			$html['class'] = $attrs['class'];
		}

		if ($rel !== null) {
			$html['rel'] = $rel;
		}

		return '<a' . $this->attrString($html) . ">{$inner}</a>";
	}

	private function image(array $node): string
	{
		$uid = $node['attrs']['uid'] ?? null;

		if (!is_string($uid) || $uid === '') {
			return '';
		}

		$asset = $this->resolver->asset($uid);

		if ($asset === null) {
			return $this->skip("unresolvable image '{$uid}'");
		}

		$src = $asset->resizable() ? $asset->sizePath($this->imageSize) : $asset->path();
		$meta = is_array($node['attrs']['meta'] ?? null) ? $node['attrs']['meta'] : [];
		$alt = $meta['alt'] ?? null;
		$title = $meta['title'] ?? null;

		if (!is_string($alt) || $alt === '') {
			$catalog = $asset->metaMap('alt');
			$alt = $catalog === null ? null : $this->resolver->localize($catalog);
		}

		$attrs = [
			'src' => $src,
			'alt' => is_string($alt) ? $alt : '',
		];

		if (is_string($title) && $title !== '') {
			$attrs['title'] = $title;
		}

		return '<img' . $this->attrString($attrs) . '>';
	}

	/** @param array<string, string> $attrs */
	private function tag(string $name, array $attrs, string $inner): string
	{
		return "<{$name}" . $this->attrString($attrs) . ">{$inner}</{$name}>";
	}

	/** @param array<string, string> $attrs */
	private function attrString(array $attrs): string
	{
		$html = '';

		foreach ($attrs as $key => $value) {
			$html .= ' ' . $key . '="' . escape($value) . '"';
		}

		return $html;
	}

	private function skip(string $notice): string
	{
		$this->notices[] = $notice;

		return '';
	}
}
