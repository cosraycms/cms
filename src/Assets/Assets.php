<?php

declare(strict_types=1);

namespace Cosray\Assets;

use Cosray\Config;
use Cosray\Exception\RuntimeException;
use Cosray\Util\Path;

class Assets
{
	public readonly string $publicDir;
	public readonly string $assetsDir;
	public readonly string $cacheDir;

	public function __construct(
		protected readonly Config $config,
	) {
		$publicDir = realpath($config->path->public);

		if ($publicDir === false) {
			throw new RuntimeException('Public directory does not exist: ' . $config->path->public);
		}

		$this->publicDir = rtrim($publicDir, '\\/');

		$this->assetsDir = Path::below($this->publicDir, $config->path->assets);
		$this->cacheDir = Path::below($this->publicDir, $config->path->cache);
	}

	public function image(string $path): Image
	{
		return new Image($this, $path);
	}
}
