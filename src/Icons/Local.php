<?php

declare(strict_types=1);

namespace Celemas\Cms\Icons;

use Celemas\Cms\Contract;

use function Celemas\Cms\escape;

final class Local implements Contract\Icons
{
	/** @var list<string> */
	private array $paths;

	/** @param array<array-key, mixed> $paths */
	public function __construct(array $paths)
	{
		$this->paths = $this->normalizePaths($paths);
	}

	/** @param array<array-key, mixed> $args */
	public function icon(string $id, array $args = []): string
	{
		$parts = $this->split($id);

		if ($parts === null) {
			return '';
		}

		foreach ($this->paths as $path) {
			$file = $this->file($path, $parts['prefix'], $parts['name']);

			if ($file === null) {
				continue;
			}

			$svg = file_get_contents($file);

			if (is_string($svg) && $this->isSvg($svg)) {
				return $this->injectAttributes($svg, $args);
			}
		}

		return '';
	}

	/**
	 * @return array{prefix: ?string, name: string}|null
	 */
	private function split(string $id): ?array
	{
		$id = trim($id);

		if (!preg_match(
			'/^(?:(?<prefix>[a-z0-9]+(?:[-_][a-z0-9]+)*):)?(?<name>[a-z0-9]+(?:[-_][a-z0-9]+)*)$/i',
			$id,
			$matches,
		)) {
			return null;
		}

		$name = strtolower((string) $matches['name']);

		if ($name === '') {
			return null;
		}

		$prefix = $matches['prefix'] ?? null;
		$prefix = is_string($prefix) && $prefix !== '' ? strtolower($prefix) : null;

		return ['prefix' => $prefix, 'name' => $name];
	}

	/**
	 * @param array<array-key, mixed> $paths
	 * @return list<string>
	 */
	private function normalizePaths(array $paths): array
	{
		$result = [];

		foreach ($paths as $path) {
			if (!is_string($path)) {
				continue;
			}

			$path = trim($path);

			if ($path === '') {
				continue;
			}

			$real = realpath($path);

			if ($real !== false && is_dir($real)) {
				$result[] = $real;
			}
		}

		return array_values(array_unique($result));
	}

	private function file(string $path, ?string $prefix, string $name): ?string
	{
		$file = $prefix === null
			? $path . DIRECTORY_SEPARATOR . $name . '.svg'
			: $path . DIRECTORY_SEPARATOR . $prefix . DIRECTORY_SEPARATOR . $name . '.svg';
		$resolved = realpath($file);

		if ($resolved === false || !is_file($resolved)) {
			return null;
		}

		$root = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

		if (!str_starts_with($resolved, $root)) {
			return null;
		}

		return $resolved;
	}

	/** @param array<array-key, mixed> $args */
	private function injectAttributes(string $svg, array $args): string
	{
		$class = $this->clean($args['class'] ?? null);
		$style = $this->mergeStyle(
			$this->clean($args['style'] ?? null),
			$this->clean($args['color'] ?? null),
		);

		if ($class === null && $style === null) {
			return $svg;
		}

		if (!preg_match('/<svg\\b[^>]*>/i', $svg, $matches, PREG_OFFSET_CAPTURE)) {
			return $svg;
		}

		$tag = $matches[0][0];
		$offset = $matches[0][1];
		$length = strlen($tag);

		if ($class !== null) {
			$tag = $this->appendAttribute($tag, 'class', $class);
		}

		if ($style !== null) {
			$tag = $this->appendAttribute($tag, 'style', $style);
		}

		return substr_replace($svg, $tag, $offset, $length);
	}

	private function appendAttribute(string $tag, string $name, string $value): string
	{
		$pattern = sprintf('/\\s%s\\s*=\\s*(?:"([^"]*)"|\'([^\']*)\')/i', preg_quote($name, '/'));

		if (preg_match($pattern, $tag, $matches) === 1) {
			$current = ($matches[1] ?? '') !== '' ? $matches[1] : $matches[2] ?? '';
			$merged = $name === 'style'
				? $this->joinStyles($current, $value)
				: trim($current . ' ' . $value);
			$replacement = sprintf(' %s="%s"', $name, escape($merged));

			return (string) preg_replace($pattern, $replacement, $tag, 1);
		}

		$injection = sprintf(' %s="%s"', $name, escape($value));
		$closer = str_ends_with($tag, '/>') ? '/>' : '>';
		$base = substr($tag, 0, -strlen($closer));

		return $base . $injection . $closer;
	}

	private function mergeStyle(?string $style, ?string $color): ?string
	{
		if ($color === null) {
			return $style;
		}

		$colorStyle = 'color: ' . $color;

		if ($style === null) {
			return $colorStyle;
		}

		return rtrim($style, '; ') . '; ' . $colorStyle;
	}

	private function joinStyles(string $base, string $append): string
	{
		$base = trim($base);
		$append = trim($append);

		if ($base === '') {
			return $append;
		}

		if ($append === '') {
			return $base;
		}

		return rtrim($base, '; ') . '; ' . ltrim($append, '; ');
	}

	private function clean(mixed $value): ?string
	{
		if (!is_scalar($value)) {
			return null;
		}

		$value = trim((string) $value);

		return $value === '' ? null : $value;
	}

	private function isSvg(string $svg): bool
	{
		return str_starts_with(ltrim($svg), '<svg');
	}
}
