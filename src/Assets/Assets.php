<?php

declare(strict_types=1);

namespace Celemas\Cms\Assets;

use Celemas\Cms\Config;
use Celemas\Cms\Util\Path;
use Celemas\Core\Request;

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

	public function file(string $path): File
	{
		return new File($this->request, $this, $path);
	}
}
