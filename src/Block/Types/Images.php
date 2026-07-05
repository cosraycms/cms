<?php

declare(strict_types=1);

namespace Cosray\Block\Types;

use Cosray\Block\RenderContext;
use Cosray\Block\Type;
use Cosray\Field\Control;
use Cosray\Value\Block;

use function Cosray\escape;

final class Images extends Type
{
	private const string THUMB = 'block-thumb';

	public function id(): string
	{
		return 'images';
	}

	public function label(): string
	{
		return 'Mehrere Bilder';
	}

	public function control(): Control
	{
		return Control::blockImages();
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
		$name = (string) ($ctx->args['thumbSize'] ?? self::THUMB);
		// Validate the name up front — a typo should fail the render,
		// not silently emit URLs the fallback route will 404.
		$ctx->owner->config()->media->sizes->get($name);
		$result = '';

		foreach ($block->data['value'] ?? [] as $f) {
			$asset = $ctx->asset((string) ($f['uid'] ?? ''));

			if ($asset === null) {
				continue;
			}

			$title = $this->mediaText($ctx, $f, 'title') ?: $this->mediaText($ctx, $f, 'alt');
			$alt = escape($title);
			$path = escape($asset->path());
			$url = $asset->resizable() ? escape($asset->sizePath($name)) : $path;

			$result .= "<div class=\"cms-blocks-images-image\"><img src=\"{$url}\" alt=\"{$alt}\" data-path-original=\"{$path}\"></div>";
		}

		if ($result) {
			return '<div class="cms-blocks-images">' . $result . '</div>';
		}

		return '';
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
