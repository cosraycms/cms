<?php

declare(strict_types=1);

namespace Cosray\Block;

use Cosray\Assets\ResizeMode;
use Cosray\Assets\SizeSpec;
use Cosray\Contract\Block;
use Cosray\Exception\RuntimeException;
use Cosray\Field;
use Cosray\Schema\Label;
use Cosray\Schema\Limit;
use Cosray\Schema\Required;
use Cosray\Schema\Translate;
use Cosray\Value\Block as BlockValue;

use function Cosray\escape;

#[Label('block:image')]
final class Image implements Block
{
	private const array LADDER = ['block-sm', 'block', 'block-lg'];
	private const string SIZES = '(min-width: 48rem) {pct}vw, 100vw';

	#[Label('block:image'), Required, Limit(1), Translate]
	protected Field\Image $image;

	public function render(BlockValue $block, RenderContext $ctx): string
	{
		$image = $block->image;
		$asset = $ctx->asset((string) ($image->unwrap()['uid'] ?? ''));

		if ($asset === null) {
			return '';
		}

		$alt = escape($image->alt() ?: strip_tags($image->title()));
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
		$sizes = escape($this->sizes($ctx, $block->layout()->span));

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

	private function sizes(RenderContext $ctx, int $span): string
	{
		$template = (string) ($ctx->args['sizes'] ?? self::SIZES);
		$pct = (int) round(($span / max($ctx->columns, 1)) * 100);

		return str_replace('{pct}', (string) $pct, $template);
	}
}
