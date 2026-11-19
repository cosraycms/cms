<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Celema\Console\Buffer;
use Celema\Console\Io;
use Cosray\Commands\MaintenanceOff;
use Cosray\Commands\MaintenanceOn;
use Cosray\Commands\MaintenanceStatus;
use Cosray\Exception\RuntimeException;
use Cosray\Maintenance;
use Cosray\Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class MaintenanceTest extends TestCase
{
	private string $dir;

	protected function setUp(): void
	{
		parent::setUp();

		$this->dir = sys_get_temp_dir() . '/cosray-maintenance-' . bin2hex(random_bytes(6));
		mkdir($this->dir);
	}

	protected function tearDown(): void
	{
		foreach (glob($this->dir . '/{,.}*', GLOB_BRACE) ?: [] as $file) {
			if (is_file($file)) {
				unlink($file);
			}
		}

		rmdir($this->dir);
		parent::tearDown();
	}

	public function testTheSiteRunsNormallyWithoutTheFile(): void
	{
		$this->assertNull($this->maintenance()->state());
	}

	public function testOnStoresTheModeAndASecretThatAdmits(): void
	{
		$maintenance = $this->maintenance();
		$secret = $maintenance->on(full: true, retry: 120, message: ' Back at ten ');
		$state = $maintenance->state();

		$this->assertNotNull($state);
		$this->assertTrue($state->full);
		$this->assertSame(120, $state->retry);
		$this->assertSame('Back at ten', $state->message);
		$this->assertNotNull($state->since);
		$this->assertTrue($state->admits($secret));
		$this->assertFalse($state->admits('other'));
		$this->assertFalse($state->admits(''));
		$this->assertStringNotContainsString($secret, (string) file_get_contents($this->file()));
		$this->assertSame([basename($this->file())], array_values(array_diff(scandir($this->dir) ?: [], ['.', '..'])));
	}

	public function testTurningItOnAgainReplacesTheStateAndItsSecret(): void
	{
		$maintenance = $this->maintenance();
		$first = $maintenance->on(full: true);
		$second = $maintenance->on();
		$state = $maintenance->state();

		$this->assertNotNull($state);
		$this->assertFalse($state->full);
		$this->assertSame(60, $state->retry);
		$this->assertNull($state->message);
		$this->assertFalse($state->admits($first));
		$this->assertTrue($state->admits($second));
	}

	public function testAFileWithoutStateRefusesWritesAndAdmitsNobody(): void
	{
		touch($this->file());
		$state = $this->maintenance()->state();

		$this->assertNotNull($state);
		$this->assertFalse($state->full);
		$this->assertSame(60, $state->retry);
		$this->assertFalse($state->admits('anything'));
	}

	public function testOffEndsTheModeAndTellsWhetherItWasOn(): void
	{
		$maintenance = $this->maintenance();
		$maintenance->on();

		$this->assertTrue($maintenance->off());
		$this->assertNull($maintenance->state());
		$this->assertFalse($maintenance->off());
	}

	public function testOnFailsWithoutItsDirectory(): void
	{
		$this->expectException(RuntimeException::class);

		new Maintenance($this->dir . '/missing/maintenance.json')->on();
	}

	public function testTheFileDefaultsToTheProjectRoot(): void
	{
		$this->assertSame(self::root() . '/maintenance.json', $this->config()->maintenance->file);
		$this->assertSame($this->file(), $this->config(['maintenance.file' => $this->file()])->maintenance->file);
	}

	public function testAllowedPathsAreNormalized(): void
	{
		$config = $this->config(['maintenance.allow' => ['health/', '/status', '/', '', 42]]);

		$this->assertSame(['/health', '/status'], $config->maintenance->allow);
	}

	public function testTheCommandsSwitchAndReportTheMode(): void
	{
		$config = $this->config([
			'maintenance.file' => $this->file(),
			'app.url' => 'https://example.com',
			'panel.path' => '/admin',
		]);
		$maintenance = Maintenance::of($config);

		$on = new Buffer();
		$this->assertSame(0, (new MaintenanceOn($config, $maintenance))(new Io($on), retry: 30, message: 'Moving'));
		$this->assertMatchesRegularExpression(
			'#https://example\.com/admin/maintenance\?bypass=([0-9a-f]{32})#',
			$on->output(),
		);
		preg_match('#bypass=([0-9a-f]{32})#', $on->output(), $match);
		$this->assertTrue($maintenance->state()?->admits($match[1]));

		$status = new Buffer();
		$this->assertSame(0, (new MaintenanceStatus($maintenance))(new Io($status)));
		$this->assertStringContainsString('writes are refused, Retry-After 30', $status->output());
		$this->assertStringContainsString('Message: Moving', $status->output());

		$off = new Buffer();
		$this->assertSame(0, (new MaintenanceOff($maintenance))(new Io($off)));
		$this->assertStringContainsString('Maintenance mode is off.', $off->output());

		$quiet = new Buffer();
		(new MaintenanceStatus($maintenance))(new Io($quiet));
		$this->assertStringContainsString('Maintenance mode is off.', $quiet->output());
	}

	private function file(): string
	{
		return $this->dir . '/maintenance.json';
	}

	private function maintenance(): Maintenance
	{
		return new Maintenance($this->file());
	}
}
