<?php

declare(strict_types=1);

namespace Cosray\Richtext;

/**
 * Writer-strict validation of richtext documents: saves and
 * migrations must produce only spec vocabulary. Readers stay
 * tolerant — this class is never used on the render path.
 */
final class Validator
{
	/** @var list<string> */
	private array $errors = [];

	/**
	 * @param array<string, string> $classes Declared paragraph classes (`richtext.classes`)
	 * @param array<string, string> $styles Declared text styles (`richtext.styles`)
	 */
	public function __construct(
		private readonly array $classes = [],
		private readonly array $styles = [],
	) {}

	/** @return list<string> Errors; empty when the document is valid. */
	public function validate(mixed $doc): array
	{
		$this->errors = [];

		if (!is_array($doc)) {
			return ['doc: not a node object'];
		}

		if (($doc['type'] ?? null) !== 'doc') {
			return ["doc: root node must have type 'doc'"];
		}

		$this->keys($doc, ['type', 'content'], 'doc');
		$this->content($doc, 'doc', 'doc');

		return $this->errors;
	}

	private function node(mixed $node, string $path): void
	{
		if (!is_array($node)) {
			$this->errors[] = "{$path}: not a node object";

			return;
		}

		$type = $node['type'] ?? null;

		if (!is_string($type) || !Spec::isNode($type) || $type === 'doc') {
			$printable = is_string($type) ? $type : gettype($type);
			$this->errors[] = "{$path}: unknown node type '{$printable}'";

			return;
		}

		if ($type === 'text') {
			$this->text($node, $path);

			return;
		}

		$allowed = ['type'];

		if (Spec::nodeDefaults($type) !== []) {
			$allowed[] = 'attrs';
		}

		if (!Spec::isLeaf($type)) {
			$allowed[] = 'content';
		}

		$this->keys($node, $allowed, $path);
		$this->attrs($node, $type, $path);

		if (!Spec::isLeaf($type)) {
			$this->content($node, $type, $path);
		}
	}

	private function text(array $node, string $path): void
	{
		$this->keys($node, ['type', 'text', 'marks'], $path);

		if (!is_string($node['text'] ?? null) || $node['text'] === '') {
			$this->errors[] = "{$path}: text node needs a non-empty string 'text'";
		}

		if (!array_key_exists('marks', $node)) {
			return;
		}

		$this->marks($node['marks'], $path);
	}

	private function marks(mixed $marks, string $path): void
	{
		if (!is_array($marks) || !array_is_list($marks)) {
			$this->errors[] = "{$path}: marks must be a list";

			return;
		}

		$seen = [];

		foreach ($marks as $i => $mark) {
			$markPath = "{$path}.marks.{$i}";

			if (!is_array($mark)) {
				$this->errors[] = "{$markPath}: not a mark object";

				continue;
			}

			$type = $mark['type'] ?? null;

			if (!is_string($type) || !Spec::isMark($type)) {
				$printable = is_string($type) ? $type : gettype($type);
				$this->errors[] = "{$markPath}: unknown mark type '{$printable}'";

				continue;
			}

			if (isset($seen[$type])) {
				$this->errors[] = "{$markPath}: duplicate mark '{$type}'";
			}

			$seen[$type] = true;
			$this->keys(
				$mark,
				$type === 'link' || $type === 'style' ? ['type', 'attrs'] : ['type'],
				$markPath,
			);

			if ($type === 'link') {
				$this->link($mark['attrs'] ?? null, $markPath);
			} elseif ($type === 'style') {
				$this->style($mark['attrs'] ?? null, $markPath);
			}
		}

		if (isset($seen['subscript'], $seen['superscript'])) {
			$this->errors[] = "{$path}: subscript and superscript exclude each other";
		}
	}

	private function link(mixed $attrs, string $path): void
	{
		if (!is_array($attrs)) {
			$this->errors[] = "{$path}: link needs attrs";

			return;
		}

		$this->attrKeys($attrs, ['href', 'node', 'asset', 'target', 'class'], $path);
		$targets = array_filter(
			[
				$attrs['href'] ?? null,
				$attrs['node'] ?? null,
				$attrs['asset'] ?? null,
			],
			static fn(mixed $value) => $value !== null,
		);

		if (count($targets) !== 1) {
			$this->errors[] = "{$path}: link needs exactly one of href/node/asset";
		}

		foreach (['href', 'node', 'asset'] as $key) {
			if (array_key_exists($key, $attrs) && (!is_string($attrs[$key]) || $attrs[$key] === '')) {
				$this->errors[] = "{$path}: link {$key} must be a non-empty string";
			}
		}

		foreach (['target', 'class'] as $key) {
			if (array_key_exists($key, $attrs) && $attrs[$key] !== null && !is_string($attrs[$key])) {
				$this->errors[] = "{$path}: link {$key} must be a string or null";
			}
		}
	}

	private function style(mixed $attrs, string $path): void
	{
		$class = is_array($attrs) ? $attrs['class'] ?? null : null;

		if (!is_string($class) || $class === '') {
			$this->errors[] = "{$path}: style needs a class";

			return;
		}

		$this->attrKeys($attrs, ['class'], $path);

		if (!isset($this->styles[$class])) {
			$this->errors[] = "{$path}: undeclared text style '{$class}'";
		}
	}

	private function attrs(array $node, string $type, string $path): void
	{
		$attrs = $node['attrs'] ?? [];

		if (!is_array($attrs)) {
			$this->errors[] = "{$path}: attrs must be an object";

			return;
		}

		match ($type) {
			'paragraph' => $this->paragraphAttrs($attrs, $path),
			'heading' => $this->headingAttrs($attrs, $path),
			'orderedList' => $this->orderedListAttrs($attrs, $path),
			'horizontalRule' => $this->horizontalRuleAttrs($attrs, $path),
			'image' => $this->imageAttrs($attrs, $path),
			default => $attrs === []
				? null
				: ($this->errors[] = "{$path}: node '{$type}' allows no attrs"),
		};
	}

	private function paragraphAttrs(array $attrs, string $path): void
	{
		$this->attrKeys($attrs, ['class', 'align'], $path);
		$this->classAttr($attrs, $path);
		$this->alignAttr($attrs, $path);
	}

	private function headingAttrs(array $attrs, string $path): void
	{
		$this->attrKeys($attrs, ['level', 'align'], $path);
		$level = $attrs['level'] ?? null;

		if (!is_int($level) || $level < 1 || $level > 6) {
			$this->errors[] = "{$path}: heading level must be an int between 1 and 6";
		}

		$this->alignAttr($attrs, $path);
	}

	private function orderedListAttrs(array $attrs, string $path): void
	{
		$this->attrKeys($attrs, ['start'], $path);

		if (array_key_exists('start', $attrs) && (!is_int($attrs['start']) || $attrs['start'] < 1)) {
			$this->errors[] = "{$path}: orderedList start must be an int >= 1";
		}
	}

	private function horizontalRuleAttrs(array $attrs, string $path): void
	{
		$this->attrKeys($attrs, ['class'], $path);
		$class = $attrs['class'] ?? null;

		if ($class !== null && (!is_string($class) || $class === '')) {
			$this->errors[] = "{$path}: horizontalRule class must be a non-empty string or null";
		}
	}

	private function imageAttrs(array $attrs, string $path): void
	{
		$this->attrKeys($attrs, ['uid', 'meta'], $path);

		if (!is_string($attrs['uid'] ?? null) || $attrs['uid'] === '') {
			$this->errors[] = "{$path}: image needs a non-empty uid";
		}

		$meta = $attrs['meta'] ?? null;

		if ($meta === null) {
			return;
		}

		if (!is_array($meta)) {
			$this->errors[] = "{$path}: image meta must be an object or null";

			return;
		}

		foreach ($meta as $key => $value) {
			if (!in_array($key, ['alt', 'title'], true)) {
				$this->errors[] = "{$path}: unknown image meta key '{$key}'";
			} elseif ($value !== null && !is_string($value)) {
				$this->errors[] = "{$path}: image meta {$key} must be a string or null";
			}
		}
	}

	private function classAttr(array $attrs, string $path): void
	{
		if (!array_key_exists('class', $attrs)) {
			return;
		}

		$class = $attrs['class'];

		if (!is_string($class) || $class === '') {
			$this->errors[] = "{$path}: class must be a non-empty string";

			return;
		}

		if ($class !== 'default' && !isset($this->classes[$class])) {
			$this->errors[] = "{$path}: undeclared paragraph class '{$class}'";
		}
	}

	private function alignAttr(array $attrs, string $path): void
	{
		if (!array_key_exists('align', $attrs)) {
			return;
		}

		$align = $attrs['align'];

		if ($align !== null && !in_array($align, Spec::ALIGNMENTS, true)) {
			$printable = is_string($align) ? $align : gettype($align);
			$this->errors[] = "{$path}: invalid align '{$printable}'";
		}
	}

	private function content(array $node, string $type, string $path): void
	{
		$model = Spec::CONTENT[$type];
		$content = $node['content'] ?? [];

		if (!is_array($content) || !array_is_list($content)) {
			$this->errors[] = "{$path}: content must be a list";

			return;
		}

		if (str_ends_with($model, '+') && $content === []) {
			$this->errors[] = "{$path}: '{$type}' must not be empty";
		}

		if ($model === 'paragraph block*' && $content !== []) {
			$first = $content[0];

			if (!is_array($first) || ($first['type'] ?? null) !== 'paragraph') {
				$this->errors[] = "{$path}: listItem must start with a paragraph";
			}
		}

		if ($model === 'paragraph block*' && $content === []) {
			$this->errors[] = "{$path}: listItem must not be empty";
		}

		foreach ($content as $i => $child) {
			$childPath = "{$path}.content.{$i}";
			$childType = is_array($child) && is_string($child['type'] ?? null) ? $child['type'] : null;

			if ($childType !== null && !$this->allowedIn($model, $childType, $i)) {
				$this->errors[] = "{$childPath}: '{$childType}' not allowed in '{$type}'";
			}

			if ($type === 'codeBlock' && is_array($child) && array_key_exists('marks', $child)) {
				$this->errors[] = "{$childPath}: codeBlock content allows no marks";
				$child = array_diff_key($child, ['marks' => true]);
			}

			$this->node($child, $childPath);
		}
	}

	private function allowedIn(string $model, string $childType, int $index): bool
	{
		return match ($model) {
			'block+' => Spec::isBlock($childType),
			'inline*' => Spec::isInline($childType),
			'listItem+' => $childType === 'listItem',
			'paragraph block*' => $index === 0 ? $childType === 'paragraph' : Spec::isBlock($childType),
			'text*' => $childType === 'text',
			default => false,
		};
	}

	private function keys(array $data, array $allowed, string $path): void
	{
		foreach (array_keys($data) as $key) {
			if (!in_array($key, $allowed, true)) {
				$this->errors[] = "{$path}: unknown key '{$key}'";
			}
		}
	}

	private function attrKeys(array $attrs, array $allowed, string $path): void
	{
		foreach (array_keys($attrs) as $key) {
			if (!in_array($key, $allowed, true)) {
				$this->errors[] = "{$path}: unknown attr '{$key}'";
			}
		}
	}
}
