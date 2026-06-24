<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Celemas\Core\Exception\HttpNotFound;
use Celemas\Core\Request;
use Cosray\Controller\Panel\Assets;
use Cosray\Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class PanelAssetTest extends TestCase
{
	public function testAssetReturnsNotFoundForPathTraversal(): void
	{
		$panel = new Assets($this->config(), $this->container(), $this->request());

		$this->throws(HttpNotFound::class);
		$panel->asset($this->request(), $this->factory(), '../composer.json');
	}

	public function testAssetReturnsNotModifiedWhenEtagMatches(): void
	{
		$panel = new Assets($this->config(), $this->container(), $this->request());
		$file = self::root() . '/panel/styles/panel.css';
		$etag = md5_file($file);
		$this->assertNotFalse($etag);
		$request = new Request($this->psrRequest()->withHeader('If-None-Match', '"' . $etag . '"'));

		$response = $panel->asset($request, $this->factory(), 'styles/panel.css');

		$this->assertSame(304, $response->getStatusCode());
		$this->assertSame(['private, max-age=3600'], $response->getHeader('Cache-Control'));
		$this->assertSame(['"' . $etag . '"'], $response->getHeader('ETag'));
		$this->assertSame([], $response->getHeader('Content-Type'));
	}

	public function testAssetReturnsCssFileWithCacheHeaders(): void
	{
		$panel = new Assets($this->config(), $this->container(), $this->request());
		$file = self::root() . '/panel/styles/panel.css';
		$etag = md5_file($file);
		$this->assertNotFalse($etag);

		$response = $panel->asset($this->request(), $this->factory(), 'styles/panel.css');

		$this->assertSame(200, $response->getStatusCode());
		$this->assertSame(['text/css'], $response->getHeader('Content-Type'));
		$this->assertSame(['private, max-age=3600'], $response->getHeader('Cache-Control'));
		$this->assertSame(['"' . $etag . '"'], $response->getHeader('ETag'));
		$this->assertNotSame([], $response->getHeader('Last-Modified'));
		$this->assertSame(file_get_contents($file), (string) $response->getBody());
	}
}
