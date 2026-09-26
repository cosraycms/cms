<?php

declare(strict_types=1);

namespace Cosray\Controller\Panel;

use Celema\Core\Exception\HttpNotFound;
use Celema\Core\Factory\Factory;
use Celema\Core\Request;
use Celema\Core\Response;
use Cosray\Exception\RuntimeException;
use Cosray\Panel\Client;
use Cosray\Plugin\Assets as PluginAssets;
use Cosray\Util\Path;

final class Assets extends Panel
{
	private const array PUBLIC_EXTENSIONS = [
		'css',
		'js',
		'mjs',
		'svg',
		'png',
		'jpg',
		'jpeg',
		'webp',
		'woff2',
		'json',
		'map',
	];

	/**
	 * The panel's own browser files from the package. The version segment
	 * only decides how long a response may be cached; any version serves the
	 * installed files.
	 */
	public function asset(Request $request, Factory $factory, string $version, string $slug): Response
	{
		$client = $this->client();
		$cacheControl = $client->immutable($version) ? 'public, max-age=31536000, immutable' : 'no-cache';

		if ($slug === Client::SPRITE) {
			$sprite = $client->sprite();
			$etag = '"' . md5($sprite) . '"';

			return (
				$this->notModified($request, $factory, $cacheControl, $etag)
					?? Response::create($factory)
						->header('Cache-Control', $cacheControl)
						->header('ETag', $etag)
						->header('Content-Type', 'image/svg+xml')
						->write($sprite)
			);
		}

		$file = $client->file($slug);

		if ($file === null) {
			throw new HttpNotFound($request);
		}

		return $this->sendFile($request, $factory, $file, $cacheControl);
	}

	public function staticAsset(Request $request, Factory $factory, string $slug): Response
	{
		return $this->serve(
			$request,
			$factory,
			$this->panelAssetsDir(),
			$slug,
			self::PUBLIC_EXTENSIONS,
			cacheControl: 'private, no-cache',
		);
	}

	public function vendor(Request $request, Factory $factory, string $plugin, string $slug): Response
	{
		$dir = $this->container->get(PluginAssets::class)->dir($plugin);

		if ($dir === null) {
			throw new HttpNotFound($request);
		}

		return $this->serve($request, $factory, $dir, $slug, self::PUBLIC_EXTENSIONS);
	}

	private function serve(
		Request $request,
		Factory $factory,
		string $root,
		string $slug,
		array $extensions = ['css', 'js', 'svg'],
		string $cacheControl = 'private, max-age=3600',
	): Response {
		try {
			$file = Path::inside($root, $slug, checkIsFile: true);
		} catch (RuntimeException $e) {
			throw new HttpNotFound($request, previous: $e);
		}

		$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

		if (!in_array($ext, $extensions, true)) {
			throw new HttpNotFound($request);
		}

		return $this->sendFile($request, $factory, $file, $cacheControl);
	}

	private function sendFile(Request $request, Factory $factory, string $file, string $cacheControl): Response
	{
		$etag = md5_file($file);
		$lastModified = filemtime($file);

		if ($etag === false || $lastModified === false) {
			throw new HttpNotFound($request);
		}

		$etag = '"' . $etag . '"';
		$notModified = $this->notModified($request, $factory, $cacheControl, $etag);
		$lastModified = gmdate('D, d M Y H:i:s', $lastModified) . ' GMT';

		if ($notModified !== null) {
			return $notModified->header('Last-Modified', $lastModified);
		}

		return Response::create($factory)
			->header('Cache-Control', $cacheControl)
			->header('ETag', $etag)
			->header('Last-Modified', $lastModified)
			->file($file);
	}

	/** A 304 when the client already has this revision, null otherwise. */
	private function notModified(Request $request, Factory $factory, string $cacheControl, string $etag): ?Response
	{
		$ifNoneMatch = array_map('trim', explode(',', $request->header('If-None-Match')));

		if (!in_array('*', $ifNoneMatch, true) && !in_array($etag, $ifNoneMatch, true)) {
			return null;
		}

		return Response::create($factory)
			->header('Cache-Control', $cacheControl)
			->header('ETag', $etag)
			->status(304);
	}
}
