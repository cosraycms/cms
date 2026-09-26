<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Exception\RuntimeException;
use Cosray\Panel\Client;
use Cosray\Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class PanelClientTest extends TestCase
{
	private string $root = '';

	protected function setUp(): void
	{
		parent::setUp();
		$this->root = sys_get_temp_dir() . '/cosray-client-' . bin2hex(random_bytes(8));
		$this->write('panel/src/panel.js', 'export {};');
		$this->write('panel/styles/panel.css', 'body {}');
		$this->write('panel/src/data.json', '{}');
		$this->write('panel/tests/setup.js', 'export {};');
		$this->write('panel/views/page.php', '<?php');
		$this->write('panel/prettier.config.js', 'export default {};');
	}

	protected function tearDown(): void
	{
		$this->remove($this->root);
		parent::tearDown();
	}

	public function testUrlsCarryTheInstalledRevision(): void
	{
		$client = $this->client();

		$this->assertMatchesRegularExpression('/\A(?:[0-9a-f]{12}|dev)\z/', $client->version());
		$this->assertSame("/cp/assets/{$client->version()}/src/panel.js", $client->url('src/panel.js'));
		$this->assertSame(
			"/assets/{$client->version()}/styles/panel.css",
			$this->client(['panel.path' => '/'])->url('/styles/panel.css'),
		);
	}

	public function testOnlyTheCurrentReleaseRevisionIsCachedForGood(): void
	{
		$client = $this->client();
		$version = $client->version();

		if ($version === 'dev') {
			$this->markTestSkipped('Composer reports no revision for cosray/cms.');
		}

		$this->assertTrue($client->immutable($version));
		$this->assertFalse($client->immutable('0123456789ab'));
		$this->assertFalse($this->client(debug: true)->immutable($version));
	}

	public function testAGitWorkingCopyIsAlwaysRevalidated(): void
	{
		$client = $this->client();
		$this->assertTrue(mkdir($this->root . '/.git'));

		$this->assertFalse($client->immutable($client->version()));
	}

	public function testServesFilesFromThePanelDirectoriesOnly(): void
	{
		$client = $this->client();

		$this->assertSame(realpath($this->root . '/panel/src/panel.js'), $client->file('src/panel.js'));
		$this->assertSame(realpath($this->root . '/panel/styles/panel.css'), $client->file('styles/panel.css'));

		foreach ([
			'tests/setup.js',
			'views/page.php',
			'prettier.config.js',
			'src/data.json',
			'src/missing.js',
			'src/../tests/setup.js',
			'src//panel.js',
			'src/./panel.js',
			'src',
			'',
		] as $slug) {
			$this->assertNull($client->file($slug), $slug);
		}
	}

	public function testImportMapPointsAtVersionedModuleUrls(): void
	{
		$this->write('panel/modules/importmap.json', json_encode([
			'imports' => [
				'prosemirror-view' => 'prosemirror-view/dist/index.js',
				'@codemirror/view' => '@codemirror/view/dist/index.js',
			],
			'scripts' => ['htmx.org/dist/htmx.js'],
			'packages' => ['prosemirror-view' => '1.42.3'],
		]));
		$client = $this->client();
		$base = "/cp/assets/{$client->version()}/modules/";

		$this->assertSame(
			[
				'imports' => [
					'prosemirror-view' => $base . 'prosemirror-view/dist/index.js',
					'@codemirror/view' => $base . '@codemirror/view/dist/index.js',
				],
			],
			$client->importMap(),
		);
		$this->assertSame([$base . 'htmx.org/dist/htmx.js'], $client->scripts());
	}

	public function testWithoutVendoredModulesTheImportMapIsEmpty(): void
	{
		$client = $this->client();

		$this->assertSame(['imports' => []], $client->importMap());
		$this->assertSame([], $client->scripts());
	}

	public function testABrokenImportMapFailsLoudly(): void
	{
		$this->write('panel/modules/importmap.json', '{"imports":');

		$this->throws(RuntimeException::class, 'Invalid panel import map');
		$this->client()->importMap();
	}

	public function testSpriteTurnsEveryIconIntoASymbol(): void
	{
		$this->write(
			'panel/icons/plus.svg',
			'<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-plus" viewBox="0 0 16 16">'
				. "\n  <path d=\"M8 4v8M4 8h8\"/>\n</svg>\n",
		);
		$this->write('panel/icons/Not An Icon.svg', '<svg viewBox="0 0 1 1"></svg>');

		$sprite = $this->client()->sprite();

		$this->assertStringStartsWith('<svg xmlns="http://www.w3.org/2000/svg">', $sprite);
		$this->assertStringContainsString(
			'<symbol id="plus" viewBox="0 0 16 16" fill="currentColor"><path d="M8 4v8M4 8h8"/></symbol>',
			$sprite,
		);
		$this->assertSame(1, substr_count($sprite, '<symbol'));
	}

	private function client(array $settings = [], bool $debug = false): Client
	{
		return new Client($this->config($settings, $debug), $this->root . '/panel');
	}

	private function write(string $path, string $content): void
	{
		$file = $this->root . '/' . $path;

		if (!is_dir(dirname($file))) {
			$this->assertTrue(mkdir(dirname($file), 0o775, true));
		}

		$this->assertNotFalse(file_put_contents($file, $content));
	}

	private function remove(string $path): void
	{
		if (!is_dir($path)) {
			return;
		}

		$files = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::CHILD_FIRST,
		);

		foreach ($files as $file) {
			$file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
		}

		rmdir($path);
	}
}
