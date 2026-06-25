<?php

declare(strict_types=1);

namespace Cosray\Controller\Panel;

use Celemas\Core\Exception\HttpNotFound;
use Celemas\Core\Factory\Factory;
use Celemas\Core\Request;
use Celemas\Core\Response;
use Cosray\Exception\RuntimeException;
use Cosray\Util\Path;

final class Assets extends Panel
{
	public function asset(Request $request, Factory $factory, string $slug): Response
	{
		return $this->serve($request, $factory, $this->panelDir, $slug);
	}

	public function build(Request $request, Factory $factory, string $slug): Response
	{
		return $this->serve($request, $factory, $this->publicPanelBuildDir(), $slug);
	}

	private function serve(Request $request, Factory $factory, string $root, string $slug): Response
	{
		try {
			$file = Path::inside($root, $slug, checkIsFile: true);
		} catch (RuntimeException $e) {
			throw new HttpNotFound($request, previous: $e);
		}

		$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

		if (!in_array($ext, ['css', 'js', 'svg'], true)) {
			throw new HttpNotFound($request);
		}

		$etag = md5_file($file);
		$lastModified = filemtime($file);

		if ($etag === false || $lastModified === false) {
			throw new HttpNotFound($request);
		}

		$etag = '"' . $etag . '"';
		$response = Response::create($factory)
			->header('Cache-Control', 'private, max-age=3600')
			->header('ETag', $etag)
			->header('Last-Modified', gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');
		$ifNoneMatch = array_map('trim', explode(',', $request->header('If-None-Match')));

		// Return 304 when the client already has this asset revision cached.
		if (in_array('*', $ifNoneMatch, true) || in_array($etag, $ifNoneMatch, true)) {
			return $response->status(304);
		}

		return $response->file($file);
	}
}
