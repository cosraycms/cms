<?php

declare(strict_types=1);

namespace Cosray\Assets;

use Celemas\Core\Request;
use Cosray\Config;
use Cosray\Util\Path;

class Assets
{
	public readonly string $publicDir;
	public readonly string $assetsDir;
	public readonly string $cacheDir;

	public function __construct(
		protected readonly Request $request,
		protected readonly Config $config,
	) {
		$this->publicDir = rtrim(realpath($config->path->public), '\\/');

		$this->assetsDir = Path::inside($this->publicDir, $config->path->assets);
		$this->cacheDir = Path::inside($this->publicDir, $config->path->cache);
	}

	public function image(string $path): Image
	{
		return new Image($this->request, $this, $path);
	}
}
