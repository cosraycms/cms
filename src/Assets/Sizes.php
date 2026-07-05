<?php

declare(strict_types=1);

namespace Cosray\Assets;

use Cosray\Exception\RuntimeException;
use Gumlet\ImageResize;

/**
 * The registry of named media sizes from the `media.sizes` config.
 *
 * Every rendition URL carries a size name, so this closed set bounds
 * what the cache fallback route will generate. The whole config is
 * validated on construction — a typo anywhere fails every request,
 * not just the template branch that uses it.
 */
final class Sizes
{
	/** Panel and block defaults; apps may override them by name. */
	private const array BUILTIN = [
		'thumb' => ['width' => 400],
		'preview' => ['width' => 1280],
		'block-sm' => ['width' => 480],
		'block' => ['width' => 960],
		'block-lg' => ['width' => 1440],
		'block-thumb' => ['crop' => [400, 267]],
	];

	private const array SINGLE = [
		'width' => ResizeMode::Width,
		'height' => ResizeMode::Height,
		'long-side' => ResizeMode::LongSide,
		'short-side' => ResizeMode::ShortSide,
	];

	private const array PAIR = [
		'crop' => ResizeMode::Crop,
		'fit' => ResizeMode::Fit,
		'resize' => ResizeMode::Resize,
	];

	private const array POSITIONS = [
		'top' => ImageResize::CROPTOP,
		'centre' => ImageResize::CROPCENTRE,
		'center' => ImageResize::CROPCENTER,
		'bottom' => ImageResize::CROPBOTTOM,
		'left' => ImageResize::CROPLEFT,
		'right' => ImageResize::CROPRIGHT,
		'topcenter' => ImageResize::CROPTOPCENTER,
	];

	/** @var array<string, SizeSpec> */
	private array $specs = [];

	public function __construct(array $config)
	{
		foreach ($config + self::BUILTIN as $name => $entry) {
			$this->specs[$name] = $this->parse((string) $name, $entry);
		}
	}

	public function get(string $name): SizeSpec
	{
		return (
			$this->specs[$name] ?? throw new RuntimeException(
				"Unknown media size '{$name}'. Add it to the `media.sizes` config.",
			)
		);
	}

	public function has(string $name): bool
	{
		return isset($this->specs[$name]);
	}

	private function parse(string $name, mixed $entry): SizeSpec
	{
		if (preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $name) !== 1) {
			throw $this->error($name, 'names must match [a-z0-9-]');
		}

		if (!is_array($entry)) {
			throw $this->error($name, 'entry must be an array');
		}

		$modes = array_intersect_key($entry, self::SINGLE + self::PAIR);

		if (count($modes) !== 1) {
			throw $this->error(
				$name,
				'exactly one mode key required ('
				. implode(
					', ',
					array_keys(self::SINGLE + self::PAIR),
				)
				. ')',
			);
		}

		$modeKey = (string) array_key_first($modes);
		$unknown = array_diff(
			array_keys($entry),
			[$modeKey, 'pos', 'quality', 'enlarge'],
		);

		if ($unknown !== []) {
			throw $this->error($name, 'unknown keys: ' . implode(', ', $unknown));
		}

		[$first, $second] = $this->dimensions($name, $modeKey, $entry[$modeKey]);

		return new SizeSpec(
			name: $name,
			mode: self::SINGLE[$modeKey] ?? self::PAIR[$modeKey],
			first: $first,
			second: $second,
			pos: $this->pos($name, $modeKey, $entry),
			quality: $this->quality($name, $entry),
			enlarge: $this->enlarge($name, $entry),
		);
	}

	/** @return array{0: int, 1: ?int} */
	private function dimensions(string $name, string $modeKey, mixed $value): array
	{
		if (isset(self::SINGLE[$modeKey])) {
			if (!is_int($value) || $value < 1) {
				throw $this->error($name, "`{$modeKey}` must be a positive int");
			}

			return [$value, null];
		}

		if (
			!is_array($value)
			|| !array_is_list($value)
			|| count($value) !== 2
			|| !is_int($value[0])
			|| !is_int($value[1])
			|| $value[0] < 1
			|| $value[1] < 1
		) {
			throw $this->error($name, "`{$modeKey}` must be `[width, height]` with positive ints");
		}

		return [$value[0], $value[1]];
	}

	private function pos(string $name, string $modeKey, array $entry): ?int
	{
		if (!array_key_exists('pos', $entry)) {
			return $modeKey === 'crop' ? ImageResize::CROPCENTER : null;
		}

		if ($modeKey !== 'crop') {
			throw $this->error($name, '`pos` is only valid with `crop`');
		}

		$pos = $entry['pos'];

		if (!is_string($pos) || !isset(self::POSITIONS[$pos])) {
			throw $this->error(
				$name,
				'`pos` must be one of '
					. implode(
						', ',
						array_keys(self::POSITIONS),
					),
			);
		}

		return self::POSITIONS[$pos];
	}

	private function quality(string $name, array $entry): ?int
	{
		if (!array_key_exists('quality', $entry)) {
			return null;
		}

		$quality = $entry['quality'];

		if (!is_int($quality) || $quality < 1 || $quality > 100) {
			throw $this->error($name, '`quality` must be an int between 1 and 100');
		}

		return $quality;
	}

	private function enlarge(string $name, array $entry): bool
	{
		if (!array_key_exists('enlarge', $entry)) {
			return false;
		}

		if (!is_bool($entry['enlarge'])) {
			throw $this->error($name, '`enlarge` must be a bool');
		}

		return $entry['enlarge'];
	}

	private function error(string $name, string $message): RuntimeException
	{
		return new RuntimeException("Invalid media size '{$name}': {$message}");
	}
}
