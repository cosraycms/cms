<?php

declare(strict_types=1);

namespace Cosray\Tests\End2End;

use Cosray\Maintenance;
use Cosray\Tests\End2EndTestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class MaintenanceModeTest extends End2EndTestCase
{
	private string $file;
	private Maintenance $maintenance;

	protected function setUp(): void
	{
		parent::setUp();

		$this->file = sys_get_temp_dir() . '/cosray-maintenance-' . bin2hex(random_bytes(6)) . '.json';
		$this->maintenance = new Maintenance($this->file);
		$this->app = $this->createApp(['api.path' => '/api', 'maintenance.file' => $this->file]);
		$this->loadFixtures('basic-types');
	}

	protected function tearDown(): void
	{
		$this->maintenance->off();

		parent::tearDown();
	}

	public function testEveryRouteRefusesWritesWhileReadsGoOn(): void
	{
		$this->maintenance->on(retry: 120);

		$page = $this->makeRequest('POST', '/');
		$this->assertSame(503, $page->getStatusCode());
		$this->assertSame('120', $page->getHeaderLine('Retry-After'));
		$this->assertStringContainsString("We'll be right back", (string) $page->getBody());

		$api = $this->makeRequest('POST', '/api/nodes', ['authToken' => 'any']);
		$this->assertSame(503, $api->getStatusCode());
		$this->assertSame('application/problem+json', $api->getHeaderLine('Content-Type'));

		$this->assertNotSame(503, $this->makeRequest('GET', '/panel/login')->getStatusCode());
		$this->assertSame(503, $this->makeRequest('POST', '/panel/login')->getStatusCode());
	}

	public function testTheFullModeRefusesReadsAndOffServesEverythingAgain(): void
	{
		$this->maintenance->on(full: true);
		$this->assertSame(503, $this->makeRequest('GET', '/panel/login')->getStatusCode());

		$this->maintenance->off();
		$this->assertNotSame(503, $this->makeRequest('GET', '/panel/login')->getStatusCode());
		$this->assertNotSame(503, $this->makeRequest('POST', '/')->getStatusCode());
	}
}
