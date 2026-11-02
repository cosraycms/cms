<?php

declare(strict_types=1);

namespace Cosray\Value;

use Cosray\Markup;
use Override;

class Video extends File
{
	#[Override]
	public function __toString(): string
	{
		return (string) $this->video();
	}

	/**
	 * The `<video>`, empty when its file is gone. Further named arguments become
	 * attributes and can replace those, like `video(autoplay: true, controls: false)`;
	 * null or false leaves one out. Spread an array for names with a dash.
	 */
	public function video(?string $class = null, string|int|bool|null ...$attributes): Markup
	{
		$asset = $this->asset($this->index);

		if ($asset === null) {
			return new Markup('');
		}

		$html = $this->printed([
			'class' => $class === '' ? null : $class,
			...[
				'controls' => true,
				'preload' => 'metadata',
				'poster' => $this->poster() ?: null,
				...$attributes,
			],
		]);
		$source = $this->printed(['src' => $asset->path(), 'type' => $asset->mime]);

		return new Markup("<video{$html}><source{$source}></video>");
	}

	/** The address of the picture shown until the video plays; empty without one. */
	public function poster(): string
	{
		$uid = $this->fileItem($this->index)['meta']['poster'] ?? null;
		$poster = is_string($uid) && $uid !== '' ? $this->owner->assets()->get($uid) : null;

		if ($poster === null) {
			return '';
		}

		return $poster->resizable() ? $poster->sizePath('preview') : $poster->path();
	}

	/** @return ?array<string, mixed> */
	#[Override]
	public function json(): ?array
	{
		$file = parent::json();

		return $file === null ? null : [...$file, 'poster' => $this->poster() ?: null];
	}
}
