<?php

declare(strict_types=1);

namespace Cosray\Storage;

use Cosray\Assets\Util;
use Cosray\Config;
use Cosray\Util\Path;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;

/**
 * Storage seam for the asset pool.
 *
 * Keys are sharded per-asset paths like `ab/abcdefghij123/logo.png`,
 * relative to the disk root. The only disk in phase 1a is `local`, rooted
 * at the public assets directory. path() resolves a key to an absolute
 * local file, which the resize pipeline, Response::file() and X-Sendfile
 * require; a non-local disk will need a temp-download strategy for those
 * before it can exist.
 */
class Storage
{
	public readonly string $disk;
	protected readonly Filesystem $filesystem;
	protected readonly string $root;

	public function __construct(Config $config)
	{
		$this->disk = 'local';
		$this->root = rtrim($config->path->public, '\\/') . '/' . trim($config->path->assets, '/');
		$this->filesystem = new Filesystem(new LocalFilesystemAdapter($this->root));
	}

	/**
	 * The pool key for an asset: `{uid[:2]}/{uid}/{slug}`. One directory
	 * per asset, so slugs can never collide. Names whose slug loses its
	 * stem (e.g. all-CJK filenames) fall back to the uid as stem.
	 */
	public static function key(string $uid, string $filename): string
	{
		$slug = Util::slug($filename);

		if ($slug === '' || str_starts_with($slug, '.')) {
			$slug = $uid . $slug;
		}

		return substr($uid, 0, 2) . "/{$uid}/{$slug}";
	}

	public function write(string $key, string $contents): void
	{
		$this->filesystem->write($key, $contents);
	}

	public function read(string $key): string
	{
		return $this->filesystem->read($key);
	}

	public function delete(string $key): void
	{
		$this->filesystem->delete($key);
	}

	public function exists(string $key): bool
	{
		return $this->filesystem->fileExists($key);
	}

	public function move(string $source, string $target): void
	{
		$this->filesystem->move($source, $target);
	}

	/** Absolute local path for an existing key; only valid on the local disk. */
	public function path(string $key): string
	{
		return Path::inside($this->root, $key, checkIsFile: true);
	}
}
