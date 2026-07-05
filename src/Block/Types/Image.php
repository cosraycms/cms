<?php

declare(strict_types=1);

namespace Cosray\Block\Types;

use Cosray\Assets\ResizeMode;
use Cosray\Assets\SizeSpec;
use Cosray\Block\RenderContext;
use Cosray\Block\Type;
use Cosray\Exception\RuntimeException;
use Cosray\Field\Control;
use Cosray\Value\Block;

use function Cosray\escape;

final class Image extends Type
{
	private const array LADDER = ['block-sm', 'block', 'block-lg'];
	private const string SIZES = '(min-width: 48rem) {pct}vw, 100vw';

	public function id(): string
	{
		return 'image';
	}

	public function label(): string
	{
		return 'Einzelbild';
	}

	public function control(): Control
	{
		return Control::blockImage();
	}

	public function init(): array
	{
		return [
			'type' => $this->id(),
			'colspan' => 12,
			'rowspan' => 1,
			'colstart' => null,
			'value' => [],
		];
	}

	public function render(Block $block, RenderContext $ctx): string
	{
		$data = $block->data;
		$asset = $ctx->asset((string) ($data['value'][0]['uid'] ?? ''));

		if ($asset === null) {
			return '';
		}

		$title = $this->mediaText($ctx, $data['value'][0] ?? [], 'title') ?: $this->mediaText(
			$ctx,
			$data['value'][0] ?? [],
			'alt',
		);
		$alt = escape($title);
		$path = escape($asset->path());

		if (!$asset->resizable()) {
			return "<img src=\"{$path}\" alt=\"{$alt}\" data-path-original=\"{$path}\">";
		}

		$specs = $this->ladder($ctx);
		$src = escape($asset->sizePath($specs[intdiv(count($specs), 2)]->name));

		if (count($specs) === 1) {
			return "<img src=\"{$src}\" alt=\"{$alt}\" data-path-original=\"{$path}\">";
		}

		$srcset = escape(implode(', ', array_map(
			static fn(SizeSpec $spec) => $asset->sizePath($spec->name) . " {$spec->first}w",
			$specs,
		)));
		$sizes = escape($this->sizes($ctx, (int) ($data['colspan'] ?? 12)));

		return (
			"<img src=\"{$src}\" srcset=\"{$srcset}\" sizes=\"{$sizes}\""
			. " alt=\"{$alt}\" data-path-original=\"{$path}\">"
		);
	}

	/**
	 * The named sizes forming the srcset ladder, ascending by width.
	 * Multi-rung ladders need `w` descriptors, so their entries must use
	 * the `width` mode; a single rung emits a plain `src` and may use
	 * any mode.
	 *
	 * @return list<SizeSpec>
	 */
	private function ladder(RenderContext $ctx): array
	{
		$names = $ctx->args['imageSizes'] ?? self::LADDER;

		if (!is_array($names) || $names === []) {
			throw new RuntimeException('Blocks error: `imageSizes` must be a non-empty list of size names');
		}

		$registry = $ctx->owner->config()->media->sizes;
		$specs = array_map(static fn($name) => $registry->get((string) $name), array_values($names));

		if (count($specs) > 1) {
			foreach ($specs as $spec) {
				if ($spec->mode !== ResizeMode::Width) {
					throw new RuntimeException(
						"Blocks error: srcset entry '{$spec->name}' must use the `width` mode",
					);
				}
			}

			usort($specs, static fn(SizeSpec $a, SizeSpec $b) => $a->first <=> $b->first);
		}

		return $specs;
	}

	private function sizes(RenderContext $ctx, int $colspan): string
	{
		$template = (string) ($ctx->args['sizes'] ?? self::SIZES);
		$pct = (int) round(($colspan / max($ctx->columns, 1)) * 100);

		return str_replace('{pct}', (string) $pct, $template);
	}

	private function mediaText(RenderContext $ctx, array $item, string $key): string
	{
		$value = $item['meta'][$key] ?? $item[$key] ?? [];

		if (!is_array($value)) {
			return is_string($value) || is_numeric($value) ? (string) $value : '';
		}

		$value = $ctx->effective($value);

		return is_string($value) || is_numeric($value) ? (string) $value : '';
	}
}
