<?php

declare(strict_types=1);

namespace Cosray\Config;

/** @phpstan-import-type MimeMap from Types */
final class Upload
{
	/** @var MimeMap|null */
	private ?array $fileCache = null;

	/** @var MimeMap|null */
	private ?array $imageCache = null;

	/** @var MimeMap|null */
	private ?array $videoCache = null;

	public function __construct(
		private readonly \Cosray\Config $config,
	) {}

	/** @var MimeMap */
	public array $file {
		get => $this->fileCache ??= $this->config->get('upload.mimetypes.file');
	}

	/** @var MimeMap */
	public array $image {
		get => $this->imageCache ??= $this->config->get('upload.mimetypes.image');
	}

	/** @var MimeMap */
	public array $video {
		get => $this->videoCache ??= $this->config->get('upload.mimetypes.video');
	}

	public int $maxSize {
		get => $this->config->get('upload.maxsize');
	}
}
