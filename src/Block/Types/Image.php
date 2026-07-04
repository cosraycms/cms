<?php

declare(strict_types=1);

namespace Cosray\Block\Types;

use Cosray\Assets\ResizeMode;
use Cosray\Assets\Size;
use Cosray\Block\RenderContext;
use Cosray\Block\Type;
use Cosray\Field\Control;
use Cosray\Value\Block;

final class Image extends Type
{
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
		$title = $this->mediaText($ctx, $data['value'][0] ?? [], 'title') ?: $this->mediaText(
			$ctx,
			$data['value'][0] ?? [],
			'alt',
		);
		$maxWidth = $ctx->args['maxImageWidth'] ?? 1440;
		$image = $ctx->assets()->image($asset->key ?? '');
		$resized = $image->resize(
			new Size((int) ($maxWidth / $ctx->columns) * (int) ($data['colspan'] ?? 12)),
			ResizeMode::Width,
			enlarge: false,
			quality: null,
		);
		$url = $resized->url(true);
		$path = $asset?->mediaPath('image') ?? '';

		return "<img src=\"{$url}\" alt=\"{$title}\" data-path-original=\"{$path}\">";
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
