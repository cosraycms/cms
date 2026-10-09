<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Celema\Console\Buffer;
use Celema\Console\Io;
use Composer\InstalledVersions;
use Cosray\Commands\PanelPublish;
use Cosray\Config;
use Cosray\Exception\RuntimeException;
use Cosray\Panel\Client;
use Cosray\Tests\TestCase;
use FilesystemIterator;
use PHPUnit\Framework\Attributes\DataProvider;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class PanelPublishTest extends TestCase
{
	private string $root;
	private array $installed;

	protected function setUp(): void
	{
		parent::setUp();
		$this->root = sys_get_temp_dir() . '/cosray-publish-' . bin2hex(random_bytes(8));
		$this->write('panel/src/panel.js', 'export {};');
		$this->write('panel/src/elements/lazy.js', 'export const lazy = true;');
		$this->write('panel/styles/panel.css', 'body {}');
		$this->write('panel/icons/check.svg', '<svg viewBox="0 0 24 24"><path d="M1 1"/></svg>');
		$this->write('panel/modules/example/index.js', 'export {};');
		$this->write('panel/modules/importmap.json', '{"composer":{"@acme/runtime":"acme/runtime/js/index.js"}}');
		$this->write('runtime/js/index.js', "export * from './translator.js';");
		$this->write('runtime/js/translator.js', 'export const translate = () => {};');
		$this->installed = InstalledVersions::getRawData();
		$installed = $this->installed;
		$installed['versions']['acme/runtime'] = ['install_path' => $this->root . '/runtime'];
		InstalledVersions::reload($installed);
		$this->write('panel/views/private.php', '<?php');
		$this->write('panel/src/private.json', '{}');
	}

	protected function tearDown(): void
	{
		InstalledVersions::reload($this->installed);
		$paths = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS),
			RecursiveIteratorIterator::CHILD_FIRST,
		);

		foreach ($paths as $path) {
			$path->isDir() && !$path->isLink() ? rmdir($path->getPathname()) : unlink($path->getPathname());
		}

		rmdir($this->root);
		parent::tearDown();
	}

	#[DataProvider('panelPaths')]
	public function testPublishesTheBrowserFilesAtTheirExistingUrls(string $panel): void
	{
		$config = $this->settings(['panel.path' => $panel, 'app.url_prefix' => '/site']);
		$client = new Client($config, $this->root . '/panel');
		$this->publish($config, $client);
		$target = $config->path->public . $client->url();

		foreach ([
			'src/panel.js',
			'src/elements/lazy.js',
			'styles/panel.css',
			'icons/check.svg',
			'modules/example/index.js',
			'composer/acme/runtime/js/index.js',
			'composer/acme/runtime/js/translator.js',
		] as $slug) {
			$this->assertFileEquals($client->file($slug), $target . $slug);
		}

		$this->assertSame($client->sprite(), file_get_contents($target . Client::SPRITE));
		foreach (['views/private.php', 'src/private.json', 'modules/importmap.json'] as $private) {
			$this->assertFileDoesNotExist($target . $private);
		}
	}

	public static function panelPaths(): iterable
	{
		yield 'default' => ['/panel'];
		yield 'nested' => ['/admin/panel/'];
		yield 'root' => ['/'];
	}

	public function testRepeatPublishingPreservesExistingRevisionsAndApplicationFiles(): void
	{
		$config = $this->settings();
		$before = new Client($config, $this->root . '/panel');
		$this->write('web/panel/application.txt', 'application content');
		$this->publish($config, $before);
		$this->publish($config, $before);
		$this->write('panel/src/panel.js', 'export const updated = true;');
		$after = new Client($config, $this->root . '/panel');
		$this->publish($config, $after);

		$this->assertNotSame($before->version(), $after->version());
		$this->assertSame('export {};', file_get_contents($config->path->public . $before->url('src/panel.js')));
		$this->assertSame(
			'export const updated = true;',
			file_get_contents($config->path->public . $after->url('src/panel.js')),
		);
		$this->assertSame('application content', file_get_contents($this->root . '/web/panel/application.txt'));
		$this->assertSame([], glob($this->root . '/web/panel/assets/.publish-*'));
	}

	public function testAnExistingRevisionIsNotOverwritten(): void
	{
		$config = $this->settings();
		$client = new Client($config, $this->root . '/panel');
		$this->publish($config, $client);
		file_put_contents($config->path->public . $client->url('src/panel.js'), 'existing bytes');

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('refusing to overwrite');
		$this->publish($config, $client);
	}

	public function testTraversalCannotPublishOutsideTheDocumentRoot(): void
	{
		$config = $this->settings(['panel.path' => '/../outside']);
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('without traversal segments');
		$this->publish($config, new Client($config, $this->root . '/panel'));
	}

	public function testDestinationSymlinksAreRejected(): void
	{
		$this->write('outside/keep.txt', 'keep');
		mkdir($this->root . '/web');
		symlink($this->root . '/outside', $this->root . '/web/panel');
		$config = $this->settings();
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('symlink');
		$this->publish($config, new Client($config, $this->root . '/panel'));
	}

	public function testSourceSymlinksCannotExposeFilesOutsideTheServedDirectory(): void
	{
		// The sibling shares the allowed directory's string prefix.
		$this->write('panel/src-private/secret.js', 'private');
		symlink($this->root . '/panel/src-private/secret.js', $this->root . '/panel/src/secret.js');
		$config = $this->settings();
		$client = new Client($config, $this->root . '/panel');
		$this->publish($config, $client);
		$this->assertFileDoesNotExist($config->path->public . $client->url('src/secret.js'));
	}

	private function publish(Config $config, Client $client): void
	{
		$this->assertSame(0, (new PanelPublish($config, $client))(new Io(new Buffer())));
	}

	private function settings(array $settings = []): Config
	{
		return $this->config(['path.public' => $this->root . '/web', ...$settings], debug: true);
	}

	private function write(string $path, string $contents): void
	{
		$file = $this->root . '/' . $path;
		if (!is_dir(dirname($file))) {
			mkdir(dirname($file), 0o755, recursive: true);
		}
		file_put_contents($file, $contents);
	}
}
