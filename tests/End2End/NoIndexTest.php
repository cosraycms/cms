<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Cosray\Tests\End2EndTestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class NoIndexTest extends End2EndTestCase
{
	protected function setUp(): void
	{
		parent::setUp();

		$this->loadFixtures('basic-types');
		$this->loadFixtures('sample-nodes');
	}

	public function testANonIndexableSiteTurnsCrawlersAway(): void
	{
		$this->app = $this->createApp(['app.disallow_robots' => true]);

		$robots = $this->makeRequest('GET', '/robots.txt');
		$this->assertSame(200, $robots->getStatusCode());
		$this->assertStringStartsWith('text/plain', $robots->getHeaderLine('Content-Type'));
		$this->assertSame("User-agent: *\nDisallow: /\n", (string) $robots->getBody());

		$page = $this->makeRequest('GET', '/');
		$this->assertResponseOk($page);
		$this->assertSame('noindex, nofollow', $page->getHeaderLine('X-Robots-Tag'));
	}

	public function testRobotsAreLeftAloneByDefault(): void
	{
		$this->assertSame(404, $this->makeRequest('GET', '/robots.txt')->getStatusCode());
		$this->assertFalse($this->makeRequest('GET', '/')->hasHeader('X-Robots-Tag'));
	}
}
