<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Composer\InstalledVersions;
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
		$this->assertSame("/panel/assets/{$client->version()}/src/panel.js", $client->url('src/panel.js'));
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
	}

	public function testAGitWorkingCopyGetsARevisionOfItsFiles(): void
	{
		$this->assertTrue(mkdir($this->root . '/.git'));
		$before = $this->client()->version();
		$this->write('panel/src/panel.js', 'export const edited = true;');
		$client = $this->client();

		$this->assertNotSame($before, $client->version());
		$this->assertTrue($client->immutable($client->version()));
		$this->assertFalse($client->immutable($before));

		// A second save within the same second and of the same size still counts.
		$this->write('panel/src/panel.js', 'export const edited = null;');

		$this->assertNotSame($client->version(), $this->client()->version());
	}

	public function testDebuggingRevisesWithTheFilesToo(): void
	{
		$before = $this->client(debug: true)->version();
		$this->write('panel/styles/panel.css', 'body { color: red }');

		$this->assertNotSame($before, $this->client(debug: true)->version());
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
		$base = "/panel/assets/{$client->version()}/modules/";

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

	public function testPreloadsFollowTheStaticImportsOfTheEntry(): void
	{
		$this->write(
			'panel/src/panel.js',
			"/** @import { Row } from './types.js' */\n"
				. "import { install } from './behaviors/rows.js';\n"
				. "import './lib/boot.js';\n"
				. "export { install };\n"
				. "void import('./elements/editor.js');\n",
		);
		$this->write(
			'panel/src/behaviors/rows.js',
			"import {\n\tturn,\n} from '../lib/turn.js';\nimport { t } from 'verba';\nimport { gone } from 'unmapped';\n"
				. "import '../../../outside.js';\n",
		);
		$this->write('outside.js', 'export {};');
		$this->write('panel/src/lib/turn.js', "export { again } from './boot.js';\n");
		$this->write('panel/src/lib/boot.js', "import { install } from '../behaviors/rows.js';\n");
		$this->write('panel/src/elements/editor.js', "import './heavy.js';\n");
		$this->write('panel/modules/importmap.json', '{"imports": {"verba": "verba/index.js"}}');
		$this->write('panel/modules/verba/index.js', "export * from './plural.js';\n");
		$this->write('panel/modules/verba/plural.js', 'export const one = 1;');
		$client = $this->client();

		$this->assertSame(
			array_map($client->url(...), [
				'src/behaviors/rows.js',
				'src/lib/boot.js',
				'src/lib/turn.js',
				'modules/verba/index.js',
				'modules/verba/plural.js',
			]),
			$client->preloads(),
		);
	}

	public function testServesTheModulesComposerPackagesShip(): void
	{
		$this->writeComposerModules(['@celema/verba' => 'celema/verba/js/src/index.js']);
		$client = $this->client();
		$src = realpath((string) InstalledVersions::getInstallPath('celema/verba') . '/js/src');

		$this->assertSame(
			['imports' => ['@celema/verba' => $client->url('composer/celema/verba/js/src/index.js')]],
			$client->importMap(),
		);
		$this->assertSame($src . '/translator.js', $client->file('composer/celema/verba/js/src/translator.js'));

		// Only the entry's directory, only scripts.
		foreach ([
			'composer/celema/verba/js/package.json',
			'composer/celema/verba/js/README.md',
			'composer/celema/verba/js/src/missing.js',
			'composer/celema/verba/js/src/theme.css',
			'composer/celema/verba/src/Translator.php',
			'composer/celema/verba/js/src/../package.json',
			'composer/acme/other/js/src/index.js',
			'composer/celema/verba',
		] as $slug) {
			$this->assertNull($client->file($slug), $slug);
		}
	}

	public function testPreloadsFollowImportsIntoComposerModules(): void
	{
		$this->write('panel/src/panel.js', "import { __ } from '@celema/verba';\n");
		$this->writeComposerModules(['@celema/verba' => 'celema/verba/js/src/index.js']);
		$client = $this->client();
		$preloads = $client->preloads();

		$this->assertContains($client->url('composer/celema/verba/js/src/index.js'), $preloads);
		$this->assertContains($client->url('composer/celema/verba/js/src/translator.js'), $preloads);
	}

	public function testAModuleMissingFromItsComposerPackageFailsLoudly(): void
	{
		$this->writeComposerModules(['@acme/gone' => 'acme/gone/js/index.js']);

		$this->throws(RuntimeException::class, 'run `composer update acme/gone`');
		$this->client()->importMap();
	}

	public function testAComposerModuleOutsideAPackageDirectoryFailsLoudly(): void
	{
		$this->writeComposerModules(['@acme/loose' => 'acme/../loose.js']);

		$this->throws(RuntimeException::class, 'Invalid panel module path');
		$this->client()->importMap();
	}

	public function testTheRevisionCoversComposerModules(): void
	{
		$without = $this->client()->version();
		$this->writeComposerModules(['@celema/verba' => 'celema/verba/js/src/index.js']);

		if ($without !== 'dev') {
			$this->assertNotSame($without, $this->client()->version());
		}

		// In a working copy, an edit to the package's files counts too.
		$this->assertTrue(mkdir($this->root . '/.git'));
		$file = (string) realpath((string) InstalledVersions::getInstallPath('celema/verba') . '/js/src/index.js');
		$mtime = (int) filemtime($file);
		$before = $this->client()->version();

		try {
			$this->assertTrue(touch($file, $mtime - 100));
			$this->assertNotSame($before, $this->client()->version());
		} finally {
			touch($file, $mtime);
		}
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

	/** @param array<string, string> $modules */
	private function writeComposerModules(array $modules): void
	{
		$this->write('panel/modules/importmap.json', (string) json_encode(['composer' => $modules]));
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
