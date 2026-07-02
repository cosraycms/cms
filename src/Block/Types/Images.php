<?php

declare(strict_types=1);

namespace Cosray\Block\Types;

use Cosray\Assets\ResizeMode;
use Cosray\Assets\Size;
use Cosray\Block\RenderContext;
use Cosray\Block\Type;
use Cosray\Field\Control;
use Cosray\Value\Block;
use Gumlet\ImageResize;

final class Images extends Type
{
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
		$result = '';

		foreach ($block->data['value'] ?? [] as $f) {
			$file = (string) ($f['file'] ?? '');
			$title = $this->mediaText($ctx, $f, 'title') ?: $this->mediaText($ctx, $f, 'alt');
			$path = $ctx->assetsPath() . $file;
			$image = $ctx->assets()->image($path);
			$resized = $image->resize(
				new Size(400, 267, cropMode: ImageResize::CROPCENTER),
				ResizeMode::Crop,
				enlarge: false,
				quality: null,
			);
			$url = $resized->url(true);

			$result .= "<div class=\"cms-blocks-images-image\"><img src=\"{$url}\" alt=\"{$title}\" data-path-original=\"{$path}\"></div>";
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
